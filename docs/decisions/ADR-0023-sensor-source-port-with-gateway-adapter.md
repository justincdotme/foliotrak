# ADR-0023: Pull sensor readings through a SensorReadingSource port with a gateway HTTP adapter

## Status
Accepted

## Date
2026-07-04

## Deciders
- Justin Christenson (developer and owner), who resolved this in the sensor integration epic's clarifications.

---

## Context

Foliotrak gained ambient temperature and humidity tracking. The readings originate outside the app: Govee H5075 BLE sensors report to a small Python gateway service (Gondola) on the household LAN, which stores them in its own SQLite database with 90-day retention and exposes a keyed REST API. Foliotrak needs those readings locally to chart them per plant and, later, to correlate them with care history.

The gateway is the most replaceable piece of the setup. A different BLE stack, a Home Assistant install, or new sensor hardware would each mean a different upstream API. Nothing in Foliotrak's domain cares which gateway produced a reading.

---

## Decision

Foliotrak is a pull-only client behind a domain port. `App\Contracts\SensorReadingSource` defines three methods in domain language: `readingsSince(mac, since)`, `discoverSensors()`, and `testConnection()`, speaking DTOs (`SensorReading`, `SensorDevice`, `SensorGatewayStatus`) and knowing nothing about HTTP. `GondolaAdapter` implements the port against the gateway's REST API (JSON parsing, `X-API-Key` header, error mapping) and is bound as a singleton in `SensorServiceProvider`, configured from `config/sensors.php`. Readings are copied into the local `sensor_readings` table; everything downstream (charts, insights) queries local MySQL only.

---

## Alternatives Considered

### Option A: Call the gateway's HTTP API directly from the command and controllers
Skip the port and let `sensors:ingest`, discovery, and the connection test each construct HTTP requests.

**Rejected because**: it couples every consumer to one gateway's JSON shapes and error behavior, scatters failure handling across call sites, and makes a gateway swap a multi-file rewrite instead of a one-class adapter.

### Option B: Chart from the gateway API without local storage
Query the gateway on demand whenever a chart renders.

**Rejected because**: every plant-detail view would depend on a remote device being awake, history would be capped at the gateway's 90-day retention, and joining readings against care events for insights would be impossible in SQL.

### Option C: Push model, where the gateway posts readings to a Foliotrak webhook
Invert the flow and make Foliotrak a receiver.

**Rejected because**: it makes the gateway aware of its consumers, complicates catch-up after a Foliotrak outage (the gateway would need its own retry queue), and adds an inbound surface for no benefit at household scale.

---

## Pros

- Swapping the sensor backend is one new adapter plus a binding change; the port, command, controllers, and UI stay untouched.
- Local storage outlives the gateway's retention window, so plant history keeps its environmental context indefinitely.
- Failure semantics live in one place: the adapter maps offline, unknown-MAC, and auth failures to empty results or a status DTO, and callers never see HTTP.
- With no `SENSOR_BASE_URL` or key configured, the subsystem is inert and the rest of the app is unaffected.

---

## Cons

- Readings exist twice (gateway SQLite and Foliotrak MySQL); the local copy is authoritative for the app but can lag by one ingest interval.
- The port and three DTOs are ceremony for what is today a single implementation.
- The port's shape assumes pull; a future push source would not fit it without rework.

---

## Consequences

### Positive
- `docs/SENSORS.md` can document a supported custom-adapter path (implement the port, rebind) without exposing internals.
- The adapter is fully testable through `Http::fake` against the real class, no gateway required.

### Negative
- Chart freshness is bounded by the ingest schedule, not by what the gateway currently knows.

### New Decisions Required
- A second concurrent reading source would force a registry decision: per-sensor source binding rather than a single global adapter.

---

## Influences

- The lean-on-Laravel convention: the adapter is plain HTTP client plus container binding, no package.
- The GBIF client (ADR-0011) set the precedent for wrapping an external API behind an app-owned class with mapped failure modes.

---

## Related Decisions

- [ADR-0024: Reach the sensor gateway over TLS with certificate verification disabled](./ADR-0024-unverified-tls-to-the-sensor-gateway.md): the transport this port's adapter uses.
- [ADR-0025: Ingest sensor readings from a per-sensor watermark over an ascending exclusive-from cursor](./ADR-0025-watermark-cursor-sensor-ingestion.md): how readings move through the port into storage.

---

## Review Date

Condition-based: revisit when a second sensor source (different gateway or push-based hardware) actually appears, or if the pull interval stops being fresh enough for a future feature.
