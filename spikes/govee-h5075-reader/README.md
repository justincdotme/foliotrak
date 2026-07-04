# Govee H5075 reader spike (FOL-100)

Logs BLE temperature/humidity broadcasts from Govee H5075 sensors into a local
SQLite database. Proof of concept for Foliotrak ambient tracking. Runs on the
host, not in the app containers, because it needs the machine's Bluetooth
adapter. Uses @stoprocent/noble, the maintained fork of noble, because the
older @abandonware fork predates current Node releases.

## Setup

Node 18+ on a Linux host with BlueZ and a working Bluetooth adapter.

    npm install

Let Node open raw HCI sockets without root (rerun after a Node upgrade, since
the capability is attached to the binary):

    sudo setcap cap_net_raw+eip "$(readlink -f "$(which node)")"

## Run

    npm start

Readings print one per line and accumulate in `readings.db`:

    sqlite3 readings.db "select * from sensors order by timestamp desc limit 10;"

Stop with Ctrl-C.

## Troubleshooting

- Adapter must be powered: `bluetoothctl show` should say `Powered: yes`.
- The reader carries two workarounds for adapter quirks seen on a Realtek
  RTL8852CU. It sets `NOBLE_REPORT_ALL_HCI_EVENTS` because noble otherwise
  mutes the advertising frames that carry the reading once the sensor's
  Apple-beacon scan response has been seen, and it restarts the scan every
  45 seconds because the adapter otherwise stops delivering reports within
  a minute of scan start. Stopping bluetoothd changes neither behavior
  (tested), and noble's D-Bus binding is not an alternative because it
  emits one discovery per device per scan and never streams advertisement
  updates.
- Multiple adapters: set `NOBLE_HCI_DEVICE_ID` to pick one.
