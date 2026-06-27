# ADR-0022: Position presented as per-plant health-by-location

## Status
Accepted

## Date
2026-06-27

## Deciders
- Justin Christenson (owner), who steered this during the Phase 5b kickoff.

---

## Context

Phase 5a (ADR-0020) surfaced position as a pairwise before-and-after-per-move insight
(`position_insights`): for each relocation, median health in the 28 days before versus after the
move. Building the UI, the owner wanted position shown the same visual way as the watering
correlation ("office reads health 4, kitchen reads health 2, see the pattern"), as a correlation
style view rather than a bespoke card. Investigation showed the owner's mental model is health
grouped by location, which is both cleaner than the pairwise before-and-after (a reading from an
earlier location can leak into a later move's "before" window) and fully reconstructable from
existing data with no schema change.

---

## Decision

Present position as a per-plant health-by-location view. The backend adds a `health_by_location`
aggregation to `GET /api/plants/{plant}/recommendations`: for each location the plant has lived in,
the median health, the sample size, and the raw readings. `LocationHealthInsight` reconstructs each
observation's location by walking the relocation chain (no relocations means the current location;
a reading before the first move takes that move's `from_location`; otherwise the `to_location` of
the latest move at or before the reading), using data the endpoint already eager-loads.

The frontend renders it on plant detail as a categorical scatter that mirrors the watering
correlation card: every reading a dot, each location's median marked, the per-location sample size
on the axis label, and non-causal copy. Because location is categorical (place names have no order)
there is no trend line; per-location medians stand in for it. It renders only when two or more
locations have readings, since one spot adds nothing the health trend does not already show.

The watering correlation stays group-pooled and numeric on the Insights page; position stays
per-plant on plant detail. They share the visual language without sharing the mechanism. The
pairwise `position_insights` from Phase 5a stays in the contract and the backend, unrendered.

---

## Alternatives Considered

### Option A: Render the pairwise before-and-after `position_insights` as a card
Show the 5a per-move median-before versus median-after directly.

**Rejected because**: the owner found it confusing; it answers "did health change around this move"
rather than "which spot suits this plant", and its fixed 28-day windows can pull a reading from a
still-earlier location into a move's "before" set.

### Option B: Make position a numeric correlation factor pooled across the group
Encode location into the numeric `CorrelationPair` shape so it rides the existing Spearman engine.

**Rejected because**: location is categorical and a move is a within-plant event. The engine casts
each x to float, so place names collapse to `0.0` and it would report a correlation of zero with a
plausible-looking band, a silent wrong answer. This is exactly what ADR-0020 set out to avoid.

---

## Consequences

- A small backend slice (`LocationHealthInsight`, one key on the recommendations payload) lands in
  a nominally UI phase, justified by the owner requirement. No migration.
- `health_by_location` and `position_insights` coexist; Phase 7 may retire the pairwise insight once
  the health-by-location view is settled.
- Position and the watering correlation read as siblings but live on different screens, since one is
  per-plant and the other is group-pooled.
