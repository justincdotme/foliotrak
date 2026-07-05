# Sensor Integration

Foliotrak tracks ambient temperature and humidity around your plants using BLE sensors (Govee H5075) collected by a [Gondola](https://github.com/justincdotme/gondola) gateway on the LAN. A scheduled command pulls readings from the gateway, stores them locally, and the plant detail UI charts the data per associated sensor.

## Environment Variables

| Variable | Purpose | Default |
|----------|---------|---------|
| `SENSOR_BASE_URL` | Gateway base URL (HTTPS, port 8443) | `''` (disabled) |
| `SENSOR_API_KEY` | Value sent in the `X-API-Key` header | `''` (disabled) |
| `SENSOR_GRANULARITY` | Minutes between scheduled ingest runs | `30` |
| `SENSOR_TLS_VERIFY` | HTTP client certificate verification | `false` |

When both `SENSOR_BASE_URL` and `SENSOR_API_KEY` are empty, the sensor subsystem is inert: no gateway requests are made and Settings reports a not configured status.

### TLS verification

The gateway serves a self-signed certificate that rotates every week or two. Traffic is still encrypted, but the gateway is not authenticated by default. This is a deliberate LAN-first tradeoff: pinning the leaf is pointless because it rotates. To authenticate the gateway later, sign its leaves with a stable local CA and set `SENSOR_TLS_VERIFY` to the path of that CA bundle.

## Setting up Gondola

Deploy the gateway on any LAN host with Bluetooth access. See the [Gondola repository](https://github.com/justincdotme/gondola) for setup instructions. Once running, configure Foliotrak:

```env
SENSOR_BASE_URL=https://your-gateway-host:8443
SENSOR_API_KEY=your-api-key-here
```

## Ingestion

The `sensors:ingest` artisan command runs on the scheduler every `SENSOR_GRANULARITY` minutes. It pages the gateway forward from a per-sensor UTC watermark, deduplicates via the `(sensor_id, recorded_at)` unique constraint, and backfills any outage up to the gateway's retention window (default 90 days). The command exits cleanly when the gateway is offline or unconfigured.

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
3. **Register** names a sensor (color auto-assigned, optional location).
4. **Add Plant** or **Edit Plant > Sensors** to associate sensors with plants.
5. **Plant Detail > Environment** tab charts temperature (F) and humidity per sensor with Day/Week/Month range selection.
