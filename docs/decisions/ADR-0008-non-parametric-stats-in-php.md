# ADR-0008: Compute statistics in PHP with math-php, no Python sidecar or ML

## Status
Accepted

## Date
2026-06-24

## Deciders
- Justin Christenson (developer and owner), who proposed and decided this in the kickoff brainstorming session.

---

## Context

The insight engine needs correlation analysis: rank correlation, event-lag feature engineering, and false-discovery control across many tested pairs. The tempting path is a Python data-science sidecar with pandas, scipy, and scikit-learn. But realistic year-one data is roughly 20 to 50 events per plant, where correlation coefficients are statistically unstable and any model fitting or ML would overfit. That honest constraint shapes the whole engine rather than being worked around.

---

## Decision

Compute statistics in PHP using `markrogoyski/math-php`: non-parametric rank correlation (Spearman or Kendall) reported with sample size and a wide confidence band, plus false-discovery-rate control when many pairs are tested at once. MySQL window functions perform the event-lag feature engineering. There is no Python sidecar, no regression, and no machine learning.

---

## Alternatives Considered

### Option A: Python data-science sidecar
Run a separate Python service using pandas, scipy, and scikit-learn for the analytics.

**Rejected because**: it adds a service, a second language, and an interface boundary for power the data volume cannot justify, and ML or regression overfits at 20 to 50 samples.

### Option B: Parametric statistics or model fitting in PHP
Fit models or use parametric correlation in PHP.

**Rejected because**: correlation is statistically unstable at this sample size, so non-parametric rank methods reported with uncertainty bands are the honest tool.

---

## Pros

- One language and runtime, with no extra service to deploy or secure (spec section 8).
- Non-parametric methods suit small, non-normal samples.
- Window-function feature engineering keeps the heavy lifting in the database.

---

## Cons

- math-php is less capable than the Python scientific stack if analytical needs ever grow.
- False-discovery control and confidence bands are built by hand rather than taken from scipy.

---

## Consequences

### Positive
- Simple deployment with no sidecar, and methods matched to the data's limits.

### Negative
- A much larger future dataset wanting real modeling would force a rethink.

### New Decisions Required
- How recommendations are derived and gated given this statistical constraint, resolved by ADR-0009.

---

## Influences

- The stated small-sample constraint (section 8).
- Single-stack deployment simplicity.
- The "non-parametric stats in PHP" convention recorded in CLAUDE.md.

---

## Related Decisions

- ADR-0009 (recommendation gating shares this statistical honesty).
- ADR-0001 (window functions run in the MySQL store).
- ADR-0007 (charts render these statistical results).

---

## Review Date

Condition-based: revisit if per-plant sample sizes grow large enough to support modeling.
