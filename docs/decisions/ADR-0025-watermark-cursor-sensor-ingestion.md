# ADR-0025: Ingest sensor readings from a per-sensor watermark over an ascending exclusive-from cursor

## Status
Accepted

## Date
2026-07-04

## Deciders
- Justin Christenson (developer and owner), who resolved this in the sensor integration epic's clarifications after the original design showed a hole-forming failure mode.

---

## Context

Foliotrak pulls readings on a schedule (`SENSOR_GRANULARITY` minutes, default 30) and can be down for hours or days at a time: deploys, host maintenance, or a dead container. The gateway keeps collecting regardless and retains 90 days. Charts and any future statistics need contiguous series; a silent hole in the middle of a week is worse than an honest gap at the edges, because nothing downstream can tell it happened.

The gateway's original readings endpoint returned newest-first with a row limit. Under that contract, a catch-up run after a long outage pulled only the newest rows while the local watermark advanced past everything older that was never fetched, permanently orphaning a hole mid-series. The gateway API was redesigned in tandem with this decision: readings are served ascending (oldest first), `from` is an exclusive lower bound, `to` an optional inclusive upper bound, and `has_more` reports whether rows remain past the page.

---

## Decision

Ingest is a watermark cursor walk. For each registered sensor, `sensors:ingest` computes `since` as the newest stored `recorded_at` (or 24 hours back when none exists), and the adapter pages forward: request `from = since`, yield the page, advance `from` to the page's last row, repeat while `has_more`. The command persists each reading as it arrives via `insertOrIgnore` against the unique `(sensor_id, recorded_at)` constraint, so a run interrupted at any point leaves a contiguous prefix and the next run resumes from the advanced watermark. Everything operates in UTC. Gateway failures never throw; the command always exits 0 and logs a fetched/new/skipped summary.

---

## Alternatives Considered

### Option A: Keep newest-first with a larger limit
Raise the row limit high enough that outages fit in one response.

**Rejected because**: any backlog beyond the limit still holes the series, so it converts a design flaw into a tuning knob that fails silently at exactly the wrong time (a long outage).

### Option B: Refetch a full sliding window every run and upsert
Always request the last N days and let dedupe discard the overlap.

**Rejected because**: it rereads an ever-larger overlap every 30 minutes forever to compensate for rare outages, and still needs the same constraint-based dedupe the cursor design gets without the waste.

### Option C: Queue-based ingest with retries and backoff
Model each sync as a queued job with failure handling.

**Rejected because**: the watermark already makes every run idempotent and resumable, so the next scheduled tick is the retry. A queue adds machinery with no behavior the cursor does not already provide.

---

## Pros

- A Foliotrak-side outage of any length backfills with no holes, bounded only by the gateway's retention.
- An interrupted run is harmless by construction: pages persist as they arrive and the watermark only ever advances over stored rows.
- Deduplication is a database constraint, not application bookkeeping; a re-run or overlap costs one ignored insert.
- The guarantee is pinned by a regression test that drives the real adapter through multi-page and interrupted-run scenarios.

---

## Cons

- The `(sensor_id, recorded_at)` key assumes at most one reading per sensor per second; a sub-second cadence source would collide.
- The watermark only moves forward: history older than the first stored reading (or the initial 24-hour default) is never fetched retroactively.
- Correctness depends on the gateway honoring ascending order and `has_more`; a source that cannot offer cursor paging cannot use this design.

---

## Consequences

### Positive
- `sensors:ingest` is safe to run by hand at any time, which makes manual backfills and live debugging trivial.
- No retry, queue, or scheduling state exists beyond the readings table itself.

### Negative
- A gateway whose clock runs behind can timestamp readings before the current watermark, and those readings are skipped as already-seen territory.

### New Decisions Required
- If a source appears that cannot serve an ascending exclusive-from cursor, ingestion for that source needs its own strategy rather than bending this one.

---

## Influences

- The observed failure mode of the original newest-first contract, which produced unfixable mid-series holes in testing.
- The database-queue-and-scheduler convention: plain scheduled artisan commands over new infrastructure.
- The testing guideline of preferring integration coverage: the regression test exercises command, adapter, paging, and constraint together.

---

## Related Decisions

- [ADR-0023: Pull sensor readings through a SensorReadingSource port with a gateway HTTP adapter](./ADR-0023-sensor-source-port-with-gateway-adapter.md): the seam this ingest strategy runs through.
- [ADR-0026: Store sensor temperature as canonical Celsius and convert to Fahrenheit in the resource layer](./ADR-0026-canonical-celsius-fahrenheit-at-resource-layer.md): the unit rules for what this pipeline stores.

---

## Review Date

Condition-based: revisit if a reading source with sub-second cadence or without cursor paging arrives, or if gateway clock skew is ever observed producing skipped readings in practice.
