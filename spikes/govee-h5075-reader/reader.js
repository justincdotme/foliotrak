'use strict';

const GOVEE_COMPANY_ID = 0xec88;

// Wire format: 2-byte company id (LE 0xEC88), a flags byte, a 24-bit
// big-endian packed value, then battery percent. The packed value carries
// temperature * 10 in the thousands and humidity * 10 in the remainder;
// its top bit flags a negative temperature.
function parseH5075(manufacturerData) {
  if (!manufacturerData || manufacturerData.length < 7) return null;
  if (manufacturerData.readUInt16LE(0) !== GOVEE_COMPANY_ID) return null;

  const packed = manufacturerData.readUIntBE(3, 3);
  const isNegative = (packed & 0x800000) !== 0;
  const magnitude = packed & 0x7fffff;

  return {
    temperatureC: (isNegative ? -1 : 1) * (Math.floor(magnitude / 1000) / 10),
    humidity: (magnitude % 1000) / 10,
    battery: manufacturerData[6],
  };
}

function main() {
  // Without this, noble mutes advertising frames once a scan response has
  // been seen, and the H5075's scan response carries an Apple beacon rather
  // than the reading; surface every report and let the parser skip the noise.
  process.env.NOBLE_REPORT_ALL_HCI_EVENTS = '1';

  const path = require('node:path');
  const Database = require('better-sqlite3');
  const noble = require('@stoprocent/noble');

  const db = new Database(path.join(__dirname, 'readings.db'));
  db.exec(`
    CREATE TABLE IF NOT EXISTS sensors (
      mac         TEXT NOT NULL,
      humidity    REAL NOT NULL,
      temperature REAL NOT NULL,
      timestamp   TEXT NOT NULL
    )
  `);
  const insertReading = db.prepare(
    'INSERT INTO sensors (mac, humidity, temperature, timestamp) VALUES (?, ?, ?, ?)'
  );

  let restartTimer;

  noble.on('stateChange', (state) => {
    if (state === 'poweredOn') {
      noble.startScanning([], true);
      // Some adapter firmware (seen on RTL8852CU) stops delivering reports
      // within a minute of scan start; restarting the scan re-arms it.
      restartTimer = setInterval(() => {
        noble.stopScanning(() => noble.startScanning([], true));
      }, 45000);
      console.log('scanning for H5075 devices (Ctrl-C to stop)...');
    } else {
      clearInterval(restartTimer);
      noble.stopScanning();
    }
  });

  noble.on('discover', (peripheral) => {
    const name = peripheral.advertisement.localName || '';
    if (!name.toLowerCase().includes('h5075')) return;

    const reading = parseH5075(peripheral.advertisement.manufacturerData);
    if (reading === null) return;

    const timestamp = new Date().toISOString();
    insertReading.run(peripheral.address, reading.humidity, reading.temperatureC, timestamp);

    const rawHex = peripheral.advertisement.manufacturerData.toString('hex');
    console.log(
      `[${timestamp}] ${peripheral.address}  ${reading.temperatureC.toFixed(1)}°C  ` +
        `${reading.humidity.toFixed(1)}%  (batt ${reading.battery}%)  raw=${rawHex}`
    );
  });

  process.on('SIGINT', () => {
    const shutdown = () => {
      db.close();
      process.exit(0);
    };
    clearInterval(restartTimer);
    // Exit from the stopScanning callback so the scan-disable command reaches
    // the adapter before the process dies; the timer covers a lost callback.
    noble.stopScanning(shutdown);
    setTimeout(shutdown, 1000);
  });
}

if (require.main === module) main();

module.exports = { parseH5075 };
