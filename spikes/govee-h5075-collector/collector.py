import asyncio
import signal
import sqlite3
from datetime import datetime, timezone
from pathlib import Path
from typing import NamedTuple

from bleak import BleakScanner

GOVEE_COMPANY_ID = 0xEC88
DB_PATH = Path(__file__).parent / "readings.db"


class Reading(NamedTuple):
    temperature_c: float
    humidity: float
    battery: int


# Payload (company id already stripped by bleak): a flags byte, a 24-bit
# big-endian packed value, then battery percent. The packed value carries
# temperature * 10 in the thousands and humidity * 10 in the remainder;
# its top bit flags a negative temperature.
def parse_h5075(payload: bytes | None) -> Reading | None:
    if payload is None or len(payload) < 5:
        return None

    packed = int.from_bytes(payload[1:4], "big")
    negative = packed & 0x800000 != 0
    magnitude = packed & 0x7FFFFF

    return Reading(
        temperature_c=(-1 if negative else 1) * (magnitude // 1000) / 10,
        humidity=(magnitude % 1000) / 10,
        battery=payload[4],
    )


def utc_now_iso() -> str:
    return (
        datetime.now(timezone.utc)
        .isoformat(timespec="milliseconds")
        .replace("+00:00", "Z")
    )


async def main() -> None:
    conn = sqlite3.connect(DB_PATH)
    conn.execute(
        """
        CREATE TABLE IF NOT EXISTS sensors (
            mac         TEXT NOT NULL,
            humidity    REAL NOT NULL,
            temperature REAL NOT NULL,
            timestamp   TEXT NOT NULL
        )
        """
    )
    conn.commit()

    def on_advertisement(device, advertisement):
        name = advertisement.local_name or ""
        if "h5075" not in name.lower():
            return

        payload = advertisement.manufacturer_data.get(GOVEE_COMPANY_ID)
        reading = parse_h5075(payload)
        if reading is None:
            return

        timestamp = utc_now_iso()
        with conn:
            conn.execute(
                "INSERT INTO sensors (mac, humidity, temperature, timestamp) VALUES (?, ?, ?, ?)",
                (device.address.lower(), reading.humidity, reading.temperature_c, timestamp),
            )
        print(
            f"[{timestamp}] {device.address.lower()}  {reading.temperature_c:.1f}°C  "
            f"{reading.humidity:.1f}%  (batt {reading.battery}%)  raw[ec88]={payload.hex()}",
            flush=True,
        )

    stop = asyncio.Event()
    loop = asyncio.get_running_loop()
    loop.add_signal_handler(signal.SIGINT, stop.set)
    loop.add_signal_handler(signal.SIGTERM, stop.set)

    async with BleakScanner(detection_callback=on_advertisement):
        print("scanning for H5075 devices (Ctrl-C to stop)...", flush=True)
        await stop.wait()

    conn.close()


if __name__ == "__main__":
    asyncio.run(main())
