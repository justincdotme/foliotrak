# ADR-0006: Use GBIF for species autocomplete, lazily cached locally

## Status
Accepted

## Date
2026-06-24

## Deciders
- Justin Christenson (developer and owner), who proposed and decided this in the kickoff brainstorming session.

---

## Context

Plant entry benefits from species autocomplete on scientific and common names, which directly serves the product's top priority of frictionless logging. Several plant-data APIs exist (Trefle, Perenual, GBIF). The app is LAN-first and should keep working offline for species already tracked, without shipping a roughly 500 MB taxonomy download.

---

## Decision

Use the GBIF `/species/suggest` endpoint (no API key, prefix search) proxied through `/api/species/suggest`, writing each result into a `species_cache` table and serving from cache on repeat. The care-data APIs (Trefle and Perenual) are not used.

---

## Alternatives Considered

### Option A: Trefle or Perenual care-data APIs
Use a plant API that also provides per-species care hints.

**Rejected because**: they are too unreliable or too feature-limited to depend on. Trefle has had extended outages, and Perenual gates data behind tiers.

### Option B: Full local GBIF taxonomy download
Host the complete GBIF taxonomy locally for fully offline lookups.

**Rejected because**: it is roughly 500 MB to host and keep updated for a single household, when lazy caching of the species actually tracked gives the same offline resilience cheaply.

### Option C: No autocomplete, freeform names only
Let the user type names with no lookup.

**Rejected because**: autocomplete materially reduces logging friction, which is the product's top priority.

---

## Pros

- No API key, free to use, and prefix search fits autocomplete directly (spec section 6).
- The lazy `species_cache` gives offline resilience for tracked species without a bulk download.
- A `gbif_key` on each plant links to a stable external taxonomy identifier.

---

## Cons

- The first lookup of a new species needs GBIF reachable, so only cached species work offline.
- GBIF is taxonomy only and carries no care data, so care hints remain out of scope.

---

## Consequences

### Positive
- Low-friction species entry and a portable, self-contained cache.

### Negative
- A dependency on GBIF availability for first-time lookups.
- Care-reference data stays deferred to a future hook (section 12).

### New Decisions Required
- If a reliable care-data source appears, a later ADR for species care reference data.

---

## Influences

- The LAN-first and offline-resilience goals (section 1).
- The frictionless-logging priority (section 1).
- The reliability assessment of Trefle and Perenual.

---

## Related Decisions

- Future ADR possible: species care reference data, if a reliable source appears (section 12 hook).

---

## Review Date

Condition-based: revisit if a reliable care-data API appears or GBIF's suggest endpoint is deprecated.
