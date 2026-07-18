# Sensor Integration

Foliotrak tracks ambient conditions around your plants using BLE sensors collected by a [Gondola](https://github.com/justincdotme/gondola) gateway on the LAN. The gateway's parser registry identifies each device family (Govee H5075 hygrometers, gondola_lux light sensors and gondola_moisture soil moisture probes) and Foliotrak assigns meaning through a per-type transformer. A scheduled command pulls readings from the gateway, stores them locally, and the plant detail UI charts the data per associated sensor.

## Environment Variables

| Variable | Purpose | Default |
|----------|---------|---------|
| `SENSOR_BASE_URL` | Gateway base URL (HTTPS) | `''` (disabled) |
| `SENSOR_API_KEY` | Shared secret for HMAC request signing (`X-Signature` + `X-Timestamp` headers) | `''` (disabled) |
| `SENSOR_GRANULARITY` | Minutes between scheduled ingest runs | `30` |
| `SENSOR_TLS_VERIFY` | HTTP client certificate verification | `true` |

When both `SENSOR_BASE_URL` and `SENSOR_API_KEY` are empty, the sensor subsystem is inert: no gateway requests are made and Settings reports a not configured status.

### TLS verification

The gateway serves a publicly valid certificate, so client verification is on by default. If your gateway still uses a self-signed certificate, set `SENSOR_TLS_VERIFY=false` to skip verification, or point it at a CA bundle path that signs the gateway's leaves.

## Setting up Gondola

Deploy the gateway on any LAN host with Bluetooth access. See the [Gondola repository](https://github.com/justincdotme/gondola) for setup instructions. Once running, configure Foliotrak:

```env
SENSOR_BASE_URL=https://your-gateway-host
SENSOR_API_KEY=your-api-key-here
```

## Ingestion

The `sensors:ingest` artisan command runs on the scheduler every `SENSOR_GRANULARITY` minutes. It pages the gateway forward from a per-sensor UTC watermark, deduplicates via the `(sensor_id, recorded_at)` unique constraint and backfills any outage up to the gateway's retention window (default 90 days). The command exits cleanly when the gateway is offline or unconfigured. The adapter accepts both gateway response generations: the flat legacy reading shape and the current one with a nested `measurements` dict plus `sensor_type`.

## Sensor Types

Each registered sensor has a Foliotrak type (`hygrometer`, `light_sensor`, or `moisture`) that selects the transformer used to normalize readings on write, hydrate them on read and describe the chart fields. The gateway separately reports a hardware identity per device (`govee_h5075`, `gondola_lux`, `gondola_moisture`), which Foliotrak stores at registration and uses to preselect the type for known hardware. Readings a sensor's transformer cannot normalize are skipped and logged instead of failing the run; a wrong sensor type is the usual cause.

Moisture sensors report a raw capacitive ADC count (0-4095, higher = drier) that is stored and charted as-is. A per-sensor calibration (Settings > Sensors > gear icon on a moisture sensor) maps anchor positions on the 1-10 soil moisture scale to raw values; observation auto-fill interpolates between anchors, defaulting to the sensor's full hardware range (4095 driest at position 1, 0 wettest at position 10) until the user calibrates.

## Writing a Custom Adapter

The default adapter (`GondolaAdapter`) is bound as a singleton in `SensorServiceProvider`. To use a different sensor source:

1. Implement `App\Contracts\SensorReadingSource`:

```php
interface SensorReadingSource
{
    /** @return iterable<SensorReading> */
    public function readingsSince(string $mac, \DateTimeInterface $since): iterable;

    /** @return list<SensorDevice> */
    public function discoverSensors(): array;

    public function testConnection(): SensorGatewayStatus;
}
```

- `readingsSince` pages all readings for a MAC address after the given timestamp.
- `discoverSensors` returns every sensor the source currently sees.
- `testConnection` reports reachability and authentication status.

2. Bind your implementation in a service provider, replacing the default:

```php
$this->app->singleton(SensorReadingSource::class, YourAdapter::class);
```

## UI Walkthrough

1. **Settings > Sensors > Test Connection** verifies reachability and key validity.
2. **Discover Sensors** lists what the gateway sees (unregistered only).
3. **Register** names a sensor and sets its type, preselected when the gateway reports known hardware (color auto-assigned, optional location).
4. A gear icon on moisture sensor rows opens the calibration modal (raw value per 1-10 anchor position, autosaves on blur; clearing a value or clicking Remove drops that anchor).
5. **Add Plant** or **Edit Plant > Sensors** to associate sensors with plants.
6. **Plant Detail > Environment** tab charts each sensor's fields per its type (temperature (F) and humidity for hygrometers, lux for light sensors, raw soil moisture for moisture probes) with Day/Week/Month range selection.
