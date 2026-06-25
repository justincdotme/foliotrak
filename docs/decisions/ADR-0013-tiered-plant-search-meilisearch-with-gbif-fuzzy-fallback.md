# ADR-0013: Tiered plant search with Meilisearch primary and a GBIF fuzzy fallback

## Status
Proposed (awaiting owner sign-off to become Accepted). When accepted, supersedes the cache-first mechanism of ADR-0011 (the `species_cache` prefix read and decision D31's `species_query_cache`) while preserving ADR-0011's outbound safety envelope.

## Date
2026-06-25

## Deciders
- Justin Christenson (developer and owner), who directed the Meilisearch refactor and selected the search architecture. Approval pending.

---

## Context

The species-suggest endpoint built in Phase 1a and hardened in 1c is exact-match after case and whitespace normalization, reading a query-keyed cache (`species_query_cache`) and calling GBIF `/species/suggest` only on a miss. Two gaps block the upcoming typeahead (Phase 1b):

1. **No typo tolerance.** Plant scientific names are Latin binomials people cannot reliably spell. A misspelling such as "monstera delicosa" (missing an `i`) returns nothing, because `/species/suggest` is prefix-based and our local match is exact.
2. **Coverage is only what was already fetched.** The local cache holds the subset of GBIF taxonomy previously searched, so prefix matching against it helps only for repeat lookups.

A key subtlety drove this decision: putting a search engine in front of the *local cache* gives typo tolerance only over species already indexed. A novel misspelling still falls through to GBIF, so the GBIF fallback endpoint, not the local engine, is what determines whether novel typos are corrected. GBIF `/species/suggest` does not correct them; GBIF's fuzzy matchers do.

Constraints: self-hosted, LAN-first, single household (D4); lean on Laravel; GBIF is keyless, IP-throttled, and offers no SLA (ADR-0006, ADR-0011); the existing `GbifClient` throttle, circuit breaker, and `User-Agent` work and must be kept; no test may reach live GBIF (ADR-0012).

---

## Decision

Introduce a `PlantSearchService` that owns all search, cache, and GBIF orchestration; the controller only validates and delegates. Search is tiered:

1. **Meilisearch (via Laravel Scout) is the primary search** over the locally indexed species cache, providing typo tolerance, prefix matching, and relevance ranking with default settings (verified: typo tolerance on, `minWordSizeForTypos` 5/9; prefix search on at indexing time). The index document holds the full display record (`gbif_key`, the three names, `rank`, `family`, `cached_at`), but only the name fields are searchable (`searchableAttributes` in `config/scout.php`). Reads are served straight from the index via Scout's `raw()`, so a typeahead keystroke is one Meilisearch call with no database round-trip; the older default of hydrating Eloquent models from the database per search was the wrong shape for a per-keystroke typeahead.
2. **On a Meilisearch miss (zero results), fall back to GBIF `/species/match?name=<query>&strict=false&verbose=true`**, the only GBIF endpoint that corrects misspellings. This was validated against live GBIF (recorded in `docs/project/gbif-endpoint-validation.md`): the typo `Monstera delicosa` matched `Monstera deliciosa` with `matchType=FUZZY` and confidence 95, while `/species/search` returned `count: 0` for that and a second typo, and `/species/suggest` is prefix-only. We accept a result only when `matchType` is `EXACT` or `FUZZY` and its confidence clears a configured threshold; the primary match plus any `alternatives` become the result set. A `matchType` of `NONE` or `HIGHERRANK`, or a low-confidence match, is a genuine no-match (an empty success, not an error). `/species/match` returns a single best match rather than a broad list, so prefix breadth for never-before-seen species comes from Meilisearch and especially from a full GBIF seed import; `/species/suggest` and `/species/search` are not used.
3. **GBIF results backfill MySQL (`species_cache`) and the Meilisearch index, then return.** The match payload maps `usageKey` to our `gbif_key` and carries `scientificName`, `canonicalName`, `rank`, and `family`; it has no vernacular name, so `common_name` is null for match-sourced rows. A `status` of `SYNONYM` is resolved to its accepted name before caching. Subsequent lookups of the same species are served locally and typo-tolerantly.

MySQL `species_cache` remains the write-side source of truth: backfills upsert into it, and the index is rebuildable from it (`scout:import`) or from the seed dump. It is not read on the search path. Plants denormalize the chosen species (`scientific_name`, `common_name`, `gbif_key` on the `plants` row), so nothing joins to `species_cache`; it is a search cache, not a relational parent. Meilisearch runs as a container in the Compose stack with a persisted data volume and a master key. The `GbifClient` safety envelope (throttle, circuit breaker, `User-Agent`) is preserved unchanged; only the endpoint it calls changes.

---

## Alternatives Considered

### Option A: Keep GBIF `/species/suggest` only, no search engine
Stay with prefix suggest and the local cache.

**Rejected because**: it does not correct typos (the core requirement) and only matches cached prefixes.

### Option B: GBIF fuzzy matching plus the existing MySQL cache, no Meilisearch
Use GBIF's fuzzy endpoint to correct typos over the full taxonomy and keep `species_cache` (MySQL) as the local store. Delivers the headline requirement with no new infrastructure, which fits a single-household app and "lean on Laravel."

**Rejected because**: the owner chose Meilisearch. Recorded honestly as the proportionate alternative: it has weaker offline typo tolerance (MySQL `LIKE` is prefix-only when GBIF is down) and cruder relevance, and it would not serve a future full GBIF taxonomy seed nearly as well as a search engine. The owner is weighing that seed dump, which tips the choice toward a real search index.

### Option C: Typesense
A comparable open-source typo-tolerant search engine.

**Rejected because**: Laravel Scout has first-class Meilisearch support and no first-party Typesense driver of equal standing; no capability advantage at this scale to justify the extra unfamiliar operational surface.

### Option D: Redis sorted sets / RediSearch
Use Redis structures for search.

**Rejected because**: plain sorted sets do not do typo tolerance; RediSearch adds a Redis module dependency and hand-rolled relevance, more work for less out-of-the-box typo and ranking behavior than Meilisearch.

### Option E: MySQL `FULLTEXT`
Use MySQL natural-language or boolean full-text indexes on the cache.

**Rejected because**: MySQL `FULLTEXT` has no edit-distance fuzzy matching; it does stemming and stopwords, not misspelling correction, so it would not fix "delicosa".

### Option F: Download the full GBIF taxonomy and index it locally (ADR-0006 Option B, ~500 MB)
Index all plant names offline for complete typo-tolerant coverage.

**Rejected because**: not required now. Noted as forward-compatible: the chosen architecture makes a future full seed import a drop-in (it populates the same index and table), which the owner is considering.

---

## Pros

- Delivers the actual requirement: Meilisearch corrects typos over seen species offline, and GBIF's fuzzy endpoint corrects novel misspellings at the source.
- Prefix matching, relevance ranking, and typo tolerance come from Meilisearch defaults with near-zero configuration.
- GBIF call volume falls as the index fills; a future full seed dump makes GBIF calls rare.
- The controller slims to validate-and-delegate; search, cache, and GBIF logic live in one testable `PlantSearchService` with a single responsibility.

---

## Cons

- New stateful infrastructure (Meilisearch container plus a persisted volume) and a new dependency (Scout plus `meilisearch/meilisearch-php`) for a single-household app, the operational weight Option B avoids.
- MySQL and Meilisearch must stay in sync: Scout handles per-model writes, but an initial import and any reindex are extra steps.
- Meilisearch must be reachable for the primary path; if it is down, search degrades to the GBIF fallback or an error.
- Typo tolerance needs words of at least 5 characters (Meilisearch default), so 3 to 4 character fragments get prefix matching only.

---

## Consequences

### Positive
- `PlantSearchService` is the single search seam for the rest of the app.
- A full GBIF taxonomy seed import becomes a drop-in that improves coverage without architecture change.
- Relevance ranking improves result quality over the old prefix match.

### Negative
- More to deploy and monitor; Meilisearch availability is now part of the search path.
- The 1c `species_query_cache` table is superseded and removed as dead code.

### New Decisions Required
- The confidence threshold for accepting a `FUZZY` match (one live data point: 95 for a single-letter typo); start conservative and tunable.
- How to resolve GBIF synonyms (`status: SYNONYM` with an accepted usage) so we cache the accepted name, not the synonym.
- Behavior when Meilisearch itself is unreachable (degrade to GBIF, or surface a degraded error per ADR-0016).
- Index-settings tuning (searchable attribute order, ranking) if relevance needs adjustment.

---

## Influences

- ADR-0006 (GBIF, lazily cached, no 500 MB download): this keeps the lazy-cache spirit but adds a real search layer; a future full seed would revisit ADR-0006 Option B.
- ADR-0011 (rate-limit-safe client): its safety envelope is preserved; its cache-first prefix mechanism is superseded.
- D4 (single household): raises the proportionality question, recorded as Option B; the owner accepted the infrastructure.
- The owner's plan to seed the full GBIF taxonomy, which favors a search engine over MySQL `LIKE`.

---

## Related Decisions

- [ADR-0006: GBIF for species autocomplete, lazily cached](./ADR-0006-gbif-species-autocomplete-lazily-cached.md): the source decision this extends.
- [ADR-0011: Rate-limit-safe GBIF client](./ADR-0011-rate-limit-safe-gbif-client.md): safety envelope preserved; cache-first prefix mechanism (and decision D31's `species_query_cache`) superseded.
- [ADR-0012: GBIF tested only with mocks](./ADR-0012-gbif-tested-only-with-mocks.md): still applies; Meilisearch and GBIF are faked or controlled in tests.
- ADR-0014 (cache invalidation), ADR-0015 (empty results), ADR-0016 (degraded errors): the companion decisions in this refactor.

---

## Review Date

Condition-based: revisit if the full GBIF seed dump lands (the GBIF fallback may become vestigial), if Meilisearch's operational cost proves unjustified at this scale (reconsider Option B), or if GBIF publishes formal rate limits.
