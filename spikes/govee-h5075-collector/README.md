# Govee H5075 collector spike (FOL-100)

Containerized Python collector that logs BLE temperature/humidity broadcasts
from Govee H5075 sensors into a local SQLite database. Companion to the Node
spike in ../govee-h5075-reader/: that one proved the payload format over raw
HCI and documented the adapter quirks of that path; this one proves the
go-forward architecture, where the host's bluetoothd does all scanning and
the collector is just a D-Bus client. The container needs no privileges
beyond a read-only mount of the host's D-Bus socket.

## Requirements

Linux host with BlueZ 5.55+ (`bluetooth.service` running, adapter powered)
and Docker with the compose plugin. No setcap, no host Python, no host
networking.

## Run

    docker compose run --rm collector

Readings print one per line and accumulate in `readings.db`:

    sqlite3 readings.db "select * from sensors order by timestamp desc limit 10;"

Stop with Ctrl-C. The container runs as uid 1000, so `readings.db` is owned
by the host user.

## Tests

    docker compose run --rm collector python -m unittest test_collector -v

## Troubleshooting

- `bluetoothctl show` on the host should say `Powered: yes`; the daemon owns
  all scanning, so if bluetoothctl sees the sensor, the collector will.
- Multiple adapters: pass `bluez={"adapter": "hci1"}` to BleakScanner.
