# ADR-0016: Degraded-search error handling on GBIF unavailability

## Status
Proposed (awaiting owner sign-off to become Accepted).

## Date
2026-06-25

## Deciders
- Justin Christenson (developer and owner), who chose a 503 over a 200-with-flag. Approval pending.

---

## Context

On a search miss the endpoint falls back to GBIF. When GBIF is unavailable (circuit breaker open, throttle saturated, any non-2xx response, or a timeout or connection error), the old code silently returned an empty array, which is indistinguishable from "no plant matches your search." The user cannot tell a genuine no-match from a broken backend, and the upcoming typeahead (Phase 1b) needs to render the right message rather than implying the plant does not exist.

The existing `GbifClient::suggest` already separates these cases: it returns an empty array on a healthy "no match" and `null` when the call was not made or failed. That contract is the hook for distinguishing them at the API boundary.

---

## Decision

On a search request, distinguish three outcomes:

1. **Results found** (local hit, or GBIF returned data): `200` with the results.
2. **Genuine no-match** (local miss, GBIF healthy, empty response): `200` with an empty result set. This is the normal "no plants match" state.
3. **Search degraded** (local miss and GBIF unavailable): **HTTP `503`** with a structured body signaling temporary degradation, so the SPA can show "Search is temporarily unavailable, please try again."

What counts as GBIF unavailable: the `GbifClient` returns `null` whenever the call is refused locally (circuit breaker open or throttle saturated) or does not come back as a success. A GBIF success is a `2xx` (in practice `200`); **any other status, not only `429` or `503`, is treated as a failure**, as are timeouts and connection errors. The client enforces this with Laravel's `->throw()` plus a catch-all, so every 4xx and 5xx trips the breaker and yields `null`, which the boundary maps to the `503`. This keeps the failure definition broad: a `400`, a `404`, an unexpected `500`, or an HTML error page all count as "unavailable," never as "no match."

Two guards on the 503:
- If Meilisearch has any hits, those are returned regardless of GBIF state. The 503 fires only when there is nothing to show **and** GBIF is unavailable.
- A stale-but-present cache hit with GBIF down returns the stale data (ADR-0014), not a 503.

The exact body shape (for example `{ "message": "...", "code": "search_degraded" }`) and whether to include a `Retry-After` header are finalized in implementation.

---

## Alternatives Considered

### Option A: `200` with a `degraded` flag
Return `200` with `{ "degraded": true, "data": [] }` and let the typeahead render a soft banner.

**Rejected because**: the owner chose the semantically correct status. A flag on a `200` mixes "success, here is your (empty) data" with "the backend failed," and relies on every client inspecting the flag.

### Option B: Keep returning an empty array (status quo)
Treat GBIF failure as no results.

**Rejected because**: it conflates "no matches" with "broken," misleading the user into thinking their plant is not in GBIF.

### Option C: `502` or `504`
Use a different 5xx code.

**Rejected because**: `503 Service Unavailable` is the precise semantic for a dependency that is temporarily unavailable, and it pairs naturally with `Retry-After`.

---

## Pros

- The frontend can show an accurate message; "no matches" (`200` empty) and "degraded" (`503`) are unambiguous.
- Degrades gracefully: cached and stale results are still served when available, so the 503 is a true last resort.
- Reuses the `GbifClient` `null`-versus-empty contract; no new failure-detection logic.

---

## Cons

- The 1b typeahead must handle a `503` on the search path as a non-fatal degraded state, which is slightly unusual for an autocomplete.
- A misconfigured client that treats any non-`200` as fatal would surface the degraded state more harshly than intended.

---

## Consequences

### Positive
- Honest UX during a GBIF outage instead of a misleading empty list.

### Negative
- The typeahead needs an explicit degraded-state branch.

### New Decisions Required
- The 503 response body shape and whether to send `Retry-After` (finalized in implementation).

---

## Influences

- ADR-0011 (fail soft to cache): extended here, fail soft when we have cache, and signal degraded only when we have nothing to show.
- The owner's choice of a correct HTTP status over a `200`-with-flag convenience.

---

## Related Decisions

- [ADR-0013: Tiered plant search](./ADR-0013-tiered-plant-search-meilisearch-with-gbif-fuzzy-fallback.md): the flow that produces these outcomes.
- [ADR-0014: Species cache TTL and synchronous refresh](./ADR-0014-species-cache-90-day-ttl-synchronous-refresh.md): a stale hit with GBIF down returns stale data, not a 503.
- [ADR-0015: Empty search results are never cached](./ADR-0015-empty-search-results-are-never-cached.md): an empty success is a normal `200`, distinct from a degraded `503`.

---

## Review Date

Condition-based: revisit if the SPA UX argues for a `200`-with-flag convention instead, or if a richer degraded payload (for example, partial cached results plus a degraded marker) proves more useful.
