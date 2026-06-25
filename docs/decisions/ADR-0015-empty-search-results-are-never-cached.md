# ADR-0015: Empty search results are never cached

## Status
Proposed (awaiting owner sign-off to become Accepted). Reverses the empty-result caching introduced in decision D31.

## Date
2026-06-25

## Deciders
- Justin Christenson (developer and owner), who directed that empty results are never cached. Approval pending.

---

## Context

Phase 1c cached empty GBIF responses (a query that matched nothing) permanently, to stop the same fruitless query from re-hitting GBIF. That created a worse failure: a permanently cached empty never self-heals. A misspelling, or a name not yet in GBIF when first searched, stays empty forever even after the correct data becomes reachable, and the only recovery is truncating the table by hand. With Meilisearch fronting search and a GBIF fuzzy fallback (ADR-0013), most misspellings now resolve to real data anyway, so caching emptiness is both harmful and largely pointless.

---

## Decision

**Empty results are never cached** anywhere, not in MySQL, not in the Meilisearch index, and not with a TTL. An empty result is always a miss. A repeated query that matches nothing re-hits GBIF each time, bounded by the existing `GbifClient` host-keyed throttle and circuit breaker. Only responses containing actual species data are written to MySQL and indexed in Meilisearch.

---

## Alternatives Considered

### Option A: Permanent empty cache (the 1c behavior)
Cache empties forever to suppress repeat GBIF calls.

**Rejected because**: it never self-heals; recovery requires a manual table truncate. This is the bug being fixed.

### Option B: Short-TTL negative cache (for example, one hour)
Remember "empty" briefly so repeats do not re-hit GBIF, then expire so it self-heals.

**Rejected because**: the owner chose strict no-caching. The `GbifClient` throttle and breaker already bound outbound volume, so a negative cache adds bookkeeping for marginal gain; with GBIF fuzzy matching, genuine empties are rare; and a planned full GBIF seed dump would make them rarer still. Recorded as the fallback if repeated empties ever stress GBIF in practice.

### Option C: Index "no result" markers in Meilisearch
Represent empties as sentinel documents.

**Rejected because**: it pollutes the search index and reproduces the same non-self-healing problem.

---

## Pros

- Self-healing: a query that is empty today returns data once the species exists or becomes fetchable, with no manual intervention.
- No stale-empty poisoning of search results.
- Simpler: no negative-cache entries, TTLs, or eviction to maintain.
- The throttle, already required for GBIF safety, is the single volume guard.

---

## Cons

- A repeated genuinely-empty query re-hits GBIF every time, bounded only by the throttle and breaker.
- Correct behavior depends on the throttle being sized sensibly (host-keyed, 30 per minute by default).

---

## Consequences

### Positive
- The canonical typo example self-heals: once the correct name is searched and indexed, later lookups (including typo-tolerant ones via Meilisearch) succeed.

### Negative
- Modest repeated GBIF traffic for queries that truly match nothing, which is acceptable for a single household behind the throttle.

### New Decisions Required
- Confirm the throttle absorbs repeated empties under real use; if not, adopt Option B (short-TTL negative cache).

---

## Influences

- ADR-0012 / decision D30: the principle that routine workloads must not hammer GBIF; here the throttle, not a cache, is the chosen guard.
- The owner's explicit directive to never cache empties.
- The planned full GBIF seed dump, which would make empties rare.

---

## Related Decisions

- [ADR-0011: Rate-limit-safe GBIF client](./ADR-0011-rate-limit-safe-gbif-client.md): the throttle and breaker are the backstop for repeated empties.
- [ADR-0013: Tiered plant search](./ADR-0013-tiered-plant-search-meilisearch-with-gbif-fuzzy-fallback.md): supersedes decision D31, which cached empties.
- [ADR-0016: Degraded-search error handling](./ADR-0016-degraded-search-error-handling-on-gbif-unavailability.md): an empty success (GBIF healthy, no match) is a normal 200, distinct from GBIF being unavailable.

---

## Review Date

Condition-based: revisit if repeated empty queries cause GBIF throttling or blocks in practice, in which case adopt a short-TTL negative cache.
