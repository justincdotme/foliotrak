# ADR-0014: Species cache invalidation with a 90-day TTL and synchronous refresh

## Status
Proposed (awaiting owner sign-off to become Accepted). Softens the "durable, never expire" stance of ADR-0011.

## Date
2026-06-25

## Deciders
- Justin Christenson (developer and owner), who chose synchronous refresh over async. Approval pending.

---

## Context

The species cache never expires. ADR-0011 treated cached taxonomy as durable, which was fine for a pure typeahead aid. But GBIF reclassifies taxa, adds species, and updates synonyms over time, so an entry cached once can drift from GBIF's current backbone with no path to refresh short of truncating the table. With Meilisearch becoming the primary search surface (ADR-0013), stale rows also feed stale search results. The refactor needs an invalidation policy.

---

## Decision

Add a `cached_at` timestamp to species cache records, set on every write and refresh, and carry it in the search document so staleness is judged from the hit without reading the database. On read, a record older than **90 days** is stale. Refresh is **synchronous**: on a stale hit, re-fetch that species from GBIF through the existing `GbifClient` (so the throttle and circuit breaker still apply), write the fresh data back (which reindexes it), and **return the GBIF result directly**. Returning the fresh records in hand keeps the read path free of a database round-trip (ADR-0013); we do not re-search or hydrate models to get the updated data.

If the synchronous refresh cannot reach GBIF (circuit breaker open, throttle saturated, timeout, or GBIF down), **return the stale rows already in hand** rather than failing. Stale data beats no data, and the breaker prevents the refresh path from hammering GBIF during an outage. A stale-but-present hit therefore never produces a degraded error (contrast ADR-0016).

---

## Alternatives Considered

### Option A: Stale-while-revalidate with an async queued refresh
Return the stale record immediately and dispatch a queued job to re-fetch and update.

**Rejected because**: the owner chose simplicity. Async adds a queue job and background moving parts; synchronous keeps the flow linear at the cost of latency on the rare stale read. Recorded as the natural upgrade path if that latency ever hurts the typeahead.

### Option B: No expiry (status quo from ADR-0011)
Keep treating the cache as permanently durable.

**Rejected because**: taxonomy drifts and reclassifications never self-heal; the cache can silently serve outdated names indefinitely.

### Option C: Hard expiry that deletes stale rows and forces a miss
Delete a record once stale so the next lookup is a clean miss.

**Rejected because**: it discards usable data and forces a blocking GBIF fetch with nothing to fall back to if GBIF is down. Serving stale while attempting a refresh is strictly safer.

---

## Pros

- Bounds staleness to roughly 90 days plus a refresh, and self-heals reclassifications and synonym updates.
- Simple: no queue, no background workers, one timestamp column and a read-path check.
- Never worse than serving stale data when GBIF is unavailable.

---

## Cons

- A stale hit pays a synchronous GBIF round-trip on the read path, adding latency to that one request under the typeahead.
- If many stale rows surface at once, refresh traffic is only bounded by the `GbifClient` throttle, so some refreshes are deferred (served stale) under load.
- 90 days is a judgement call, not a measured optimum.

---

## Consequences

### Positive
- Search results reflect reasonably current taxonomy without operator intervention.
- The refresh reuses the existing safety envelope, so it cannot become a GBIF-hammering path.

### Negative
- Read-path latency on stale hits; the throttle can defer a refresh (acceptable: serve stale).

### New Decisions Required
- Whether 90 days is the right window once real usage exists.
- Whether to move to async stale-while-revalidate if synchronous refresh latency degrades the typeahead.

---

## Influences

- ADR-0011 (durable cache): this softens it with a bounded TTL.
- The owner's preference for fewer moving parts over async background refresh.
- The `GbifClient` throttle and breaker, which make a synchronous refresh safe to attempt on the read path.

---

## Related Decisions

- [ADR-0011: Rate-limit-safe GBIF client](./ADR-0011-rate-limit-safe-gbif-client.md): durability stance softened here.
- [ADR-0013: Tiered plant search](./ADR-0013-tiered-plant-search-meilisearch-with-gbif-fuzzy-fallback.md): the search layer this keeps fresh.
- [ADR-0016: Degraded-search error handling](./ADR-0016-degraded-search-error-handling-on-gbif-unavailability.md): a stale hit with GBIF down returns stale data, not a degraded error.

---

## Review Date

Condition-based: revisit if 90 days proves too aggressive or too lax in practice, or if synchronous refresh latency degrades the typeahead enough to justify async stale-while-revalidate.
