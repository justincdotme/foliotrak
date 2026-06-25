# ADR-0011: Roll our own rate-limit-safe, cache-first GBIF client

## Status
Accepted

## Date
2026-06-24

## Deciders
- Justin Christenson (developer and owner), who set this constraint while scoping the Phase 1a GBIF integration.

---

## Context

ADR-0006 chose GBIF `/species/suggest`, proxied through `/api/species/suggest` and lazily cached in `species_cache`. That decision settled the source and the cache. It did not settle how the app talks to GBIF safely, and the operational reality makes that its own decision.

GBIF takes no API key and publishes no fixed or guaranteed rate limit. Their own guidance is that rapid or numerous queries to the search APIs (suggest is one of these) may be rate limited depending on server load, with no availability guarantee. Throttling and blocking are applied by IP at GBIF's discretion. There is no official PHP SDK, and the third-party wrappers are not worth a dependency for a single endpoint.

The failure mode that matters: a typeahead fires on every keystroke. A naive proxy turns one user typing into a burst of GBIF calls, and a malfunctioning client (a render loop, a retry storm) can hit GBIF hard enough to get the household's single public IP throttled or blocked. Because the install is LAN-first behind one shared IP, a block is a whole-household outage of the feature, not a per-user inconvenience.

---

## Decision

Build a thin first-party GBIF client over Laravel's HTTP client. No SDK, no third-party wrapper: one endpoint, one client class, fully under our control and trivial to fake in tests (ADR-0012).

Talk to GBIF cache-first and conservatively:

- **Cache-first.** `/api/species/suggest` answers from `species_cache` and reaches GBIF only on a cache miss. A normalized query key (lowercased, trimmed, collapsed whitespace) maps repeat and near-repeat prefixes onto cached results. Cached taxonomy is treated as durable; the read path does not expire it.
- **Self-throttle well below anything GBIF would notice.** The SPA debounces the typeahead and requires a minimum query length before it calls our endpoint at all, so a keystroke burst collapses to one request. The server then caps the outbound GBIF call with a conservative rate limiter keyed on the GBIF host (not per user), sized far under any plausible GBIF threshold. When the limiter is saturated we serve cache or an empty suggestion set rather than forward a burst.
- **Identify politely.** Send a descriptive `User-Agent` naming the app and a contact, so GBIF can reach the operator rather than silently block.
- **Back off, never retry-storm.** On `429`, `503`, or a timeout, fail soft (serve cache, return empty) and apply exponential backoff behind a short circuit breaker, so repeated failures stop calling GBIF for a cool-down window instead of hammering it.
- **Degrade, never hard-fail.** If GBIF is unreachable or throttled, suggestions fall back to `species_cache` and the user can still enter a free-form name. Creating a plant never blocks on GBIF.

---

## Alternatives Considered

### Option A: A third-party GBIF PHP package
Pull a community wrapper instead of writing a client.

**Rejected because**: there is no maintained first-class option, and adding a dependency to wrap a single GET is more surface for less control. A thin client we own is smaller and trivially mockable.

### Option B: Naive pass-through proxy
Call GBIF on every suggest request, with no cache gate and no throttle.

**Rejected because**: it ties our request rate to user keystrokes and front-end bugs, which is the exact behavior that earns an IP block. Cache-first plus an outbound throttle breaks that coupling.

### Option C: React to 429s only
Trust GBIF to push back and slow down once we see throttling responses.

**Rejected because**: GBIF gives no guarantees and blocks at its discretion, so by the time 429s appear the IP may already be flagged. Staying conservative by default is cheaper than recovering from a block.

---

## Pros

- The household IP cannot get GBIF-blocked by normal use or a front-end bug, because outbound volume is capped server-side and decoupled from keystrokes.
- One small client class we own, easy to reason about and to fake in tests (ADR-0012).
- Suggestions stay fast and keep working offline for already-tracked species through the cache.

---

## Cons

- More moving parts than a pass-through: a normalized cache key, an outbound limiter, and backoff plus circuit-breaker logic to build and keep correct.
- A conservative outbound limit can, during a rare burst of genuinely novel lookups, make some first-time suggestions briefly fall back to cache-only. Acceptable: the ban risk outweighs marginal first-lookup latency.

---

## Consequences

### Positive
- Phase 1a's species work is a real integration with explicit resilience, not a thin proxy.

### Negative
- Cache-key normalization and limiter sizing need testing and sane defaults; both live in the Phase 1a build.

### New Decisions Required
- A cache refresh or expiry policy if taxonomy staleness ever matters. Out of scope now; the cache is treated as durable.

---

## Influences

- ADR-0006 (GBIF as the source, lazily cached): this settles the client and safety envelope that ADR left open.
- The LAN-first, single-household model: one shared public IP makes an IP-level block a whole-household outage, which raises the stakes on politeness.
- GBIF's stated position that search-API queries may be throttled by server load, with no guarantee.

---

## Related Decisions

- [ADR-0006: Use GBIF for species autocomplete, lazily cached locally](./ADR-0006-gbif-species-autocomplete-lazily-cached.md): the source decision this hardens.
- [ADR-0012: Test the GBIF integration only against mocks](./ADR-0012-gbif-tested-only-with-mocks.md): the test-side corollary; mocks are what let us build and exercise this without ever calling GBIF from a test loop.

---

## Review Date

Condition-based: revisit if GBIF publishes formal rate limits or an authenticated tier, or if suggestion volume ever justifies a bulk taxonomy load (the rejected ADR-0006 Option B).
