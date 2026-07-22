# ADR-0026: Store sensor temperature as canonical Celsius and convert to Fahrenheit in the resource layer

## Status
Accepted

## Date
2026-07-04

## Deciders
- Justin Christenson (developer and owner), who resolved this in the sensor integration epic's clarifications.

---

## Context

The Govee sensors and the gateway report temperature in Celsius at one-decimal precision. The household reads Fahrenheit, and the Environment chart's axis is labeled in Fahrenheit. Something has to convert, and where that happens determines what future consumers of the data inherit.

The app already settled this class of question once: weight is stored as canonical grams and split into lb/oz/g at the API layer (a non-negotiable convention). Sensor temperature is the same shape of problem.

---

## Decision

`sensor_readings.temperature` stores Celsius exactly as the gateway reports it, `decimal(4,1)`. The plant readings endpoint converts at the transform step (`round($c * 9/5 + 32, 1)`) and emits `temperature_f`; the frontend consumes `temperature_f` and never converts. Humidity needs no conversion and passes through as-is.

---

## Alternatives Considered

### Option A: Store Fahrenheit
Convert once at ingest and store what the household reads.

**Rejected because**: it bakes a display preference into storage, loses the source's native precision behind a lossy round-trip, and would make any future Celsius display or physical-unit computation start from converted data.

### Option B: Store both units
Add a second column and skip runtime conversion.

**Rejected because**: two columns for one fact invites drift and buys nothing; the conversion is one multiplication at read time.

### Option C: Convert in the frontend
Ship Celsius over the API and let each consumer convert.

**Rejected because**: it spreads unit logic into every current and future consumer, and a mixed API (Celsius numbers under Fahrenheit labels) is exactly the kind of silent mismatch charts will not surface.

---

## Pros

- Storage matches the source of truth at native precision; nothing is lost at ingest.
- Future statistics and insights operate on the physical canonical unit without un-converting display values.
- A display-unit preference, if one ever ships, is a resource-layer change with zero migration.
- The rule is predictable because it is the same rule weight already follows.

---

## Cons

- Raw table reads look wrong to a Fahrenheit-thinking operator until they remember the convention.
- Every new endpoint that exposes temperature must remember to convert at the transform step; forgetting produces plausible-looking but wrong numbers.

---

## Consequences

### Positive
- The canonical-unit convention now covers both measured quantities in the app (mass and temperature), so the next one (volume, light) has an obvious precedent.

### Negative
- API consumers get Fahrenheit only; a future consumer wanting Celsius needs a new field or parameter rather than reading what exists.

### New Decisions Required
- If a per-user measurement preference ever ships, decide where the unit toggle lives (per-user setting resolved at the resource layer, following the same pattern).

---

## Influences

- The canonical-grams weight convention, which this mirrors deliberately.
- The gateway's native Celsius reporting: storing anything else would mean converting at the least-trusted boundary.

---

## Related Decisions

- [ADR-0025: Ingest sensor readings from a per-sensor watermark over an ascending exclusive-from cursor](./ADR-0025-watermark-cursor-sensor-ingestion.md): the pipeline that writes these values.

---

## Review Date

No review date. This is a foundational unit convention; revisit only if a per-user measurement preference feature lands.
