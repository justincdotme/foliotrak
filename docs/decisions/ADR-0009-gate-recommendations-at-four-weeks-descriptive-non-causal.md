# ADR-0009: Gate recommendations at four weeks, descriptive and non-causal

## Status
Accepted

## Date
2026-06-24

## Deciders
- Justin Christenson (developer and owner), who proposed and decided this in the kickoff brainstorming session.

---

## Context

Users will want schedule recommendations early, but a recommendation built from a handful of events is noise dressed as advice. With roughly 20 to 50 events per plant in year one, the engine has to resist overclaiming. This decision sets when recommendations appear, how they are computed, and how they are phrased.

---

## Decision

A plant must have at least four weeks of history for a care type before its schedule recommendation appears. Below that threshold the UI shows a "keep logging, N days to go" countdown instead of a number. Recommendations are descriptive and robust: the median interval and the median amount, surfaced as "water about every 6 days, about 200 ml," with no model fitting. All insight copy is strictly non-causal, using "coincided with," "may indicate," and "potential factor," never "caused" or "leads to," and always showing sample size and uncertainty.

---

## Alternatives Considered

### Option A: Show recommendations immediately from any data
Surface a schedule as soon as a few events exist.

**Rejected because**: tiny-sample recommendations mislead, where the countdown sets honest expectations instead.

### Option B: Model-fitted schedules
Fit a regression on cadence to produce a schedule.

**Rejected because**: it overfits at this sample size (see ADR-0008).

### Option C: Causal phrasing
Tell the user that one action caused an outcome.

**Rejected outright**: correlation at this scale cannot support causation, and overclaiming erodes trust.

---

## Pros

- The four-week gate keeps recommendations from forming on noise (spec section 8).
- The median is robust to outliers and easy to explain.
- Enforced non-causal language keeps the product honest and trustworthy.

---

## Cons

- New plants show no recommendation for the first four weeks, which is intentional friction.
- The median ignores trends and seasonality a richer model might catch, which is acceptable at this scale.

---

## Consequences

### Positive
- The recommendations users see are defensible, and copy review enforces the language rule.

### Negative
- The gate and uncertainty framing must be carried through UI copy consistently, which is a hard rule in copy review.

### New Decisions Required
- Thresholds for the most data-hungry features, such as fertilizer optimization, are gated harder and framed as future tuning.

---

## Influences

- ADR-0008 (the same small-sample statistical honesty).
- The insight-language discipline recorded in CLAUDE.md and section 8.

---

## Related Decisions

- ADR-0008 (the statistical method behind this gating).
- ADR-0007 (charts present the uncertainty bands these recommendations rely on).

---

## Review Date

Condition-based: revisit the four-week threshold once real per-plant data shows whether it is too strict or too loose.
