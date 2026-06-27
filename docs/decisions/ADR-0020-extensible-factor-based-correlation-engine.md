# ADR-0020: Extensible factor-based correlation engine, watering and position first

## Status
Accepted

## Date
2026-06-26

## Deciders
- Justin Christenson (owner), who approved this during the Phase 5a kickoff.

---

## Context

Spec section 8 enumerates several correlation pairs and a "did moving help" relocation correlation. The owner wants the engine to grow over time (temperature, light, fertilizer) without rework, starting with the two factors that bite most in real life: watering cadence and position. The numeric `CorrelationPair` contract fits numeric factors that take a rank correlation, but not a categorical one like location, and a single plant's move is a within-plant before-and-after question rather than a pooled correlation.

---

## Decision

Build a factor-based correlation engine. A `Factor` declares how to extract, across a set of plants, the paired samples relating one input to plant health.

- Numeric factors (watering interval now; light, NPK, temperature later) are pooled across a tag group and reported as a `CorrelationPair`: Spearman rank correlation, a Fisher-z confidence band, the sample size, the raw observation points, and a Benjamini-Hochberg false-discovery flag applied across every tested pair. Adding a numeric factor is registering one class, not changing the engine.
- Position is handled where it is honest: as a per-plant "did moving help" before-and-after-move health comparison on the recommendations endpoint, not as a numeric group correlation, because location is categorical and a move is a within-plant event.

The group `correlation_pairs` ship the watering-interval factor now; the position insight ships per plant now. The remaining enumerated pairs and the fertilizer-effectiveness optimizer are deferred, since the registry makes each a small later addition once the data earns it.

---

## Alternatives Considered

### Option A: Hard-code the enumerated pairs inline in the controller
Compute each pair directly in `GroupInsightsController`.

**Rejected because**: it is not extensible, it puts untested math in a controller, and the pair list is meant to grow.

### Option B: Force position into the numeric `CorrelationPair` shape
Encode a moved-or-not flag as a point-biserial correlation.

**Rejected because**: it is a strained encoding of a categorical, within-plant signal. The before-and-after insight is clearer and more honest.

### Option C: Build all enumerated pairs plus the fertilizer optimizer now
Ship the full section-8 matrix in this phase.

**Rejected for this phase because**: most pairs would sit empty on realistic data. The factor registry makes each a small later add as the data accrues, matching the spec's own "lands after the basics earn their data" framing.

---

## Pros

- Extensible by design, which is the owner's explicit requirement.
- Each factor is unit-tested in isolation; the band and false-discovery control are computed once for every numeric factor.
- Position is surfaced where it is statistically defensible, per plant.

---

## Cons

- Two surfaces carry "correlation": the numeric group pairs and the per-plant position insight.
- A categorical group comparison (health by location pooled across a group) has no contract slot yet and waits for Phase 7.

---

## Consequences

### Positive
- Adding temperature, light, or fertilizer later is a one-class change.

### Negative
- A categorical group view will need its own presentation, deferred to Phase 7.

### New Decisions Required
- The `CorrelationPair` contract gains the raw points and the false-discovery flag (recorded in `decisions.md`).

---

## Influences

- ADR-0008 (non-parametric stats in PHP) and ADR-0009 (small-sample honesty).
- The owner's stated priority of watering and position, and the request for an extensible engine.

---

## Related Decisions

- ADR-0008, ADR-0018 (the statistical method and where it runs).
- ADR-0019 (the recommendation engine that shares this honesty).
- ADR-0007 (the charts that present these results).

---

## Review Date

Condition-based: revisit when a second numeric factor or a categorical group view is added.
