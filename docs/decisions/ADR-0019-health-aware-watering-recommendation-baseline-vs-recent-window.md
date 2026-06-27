# ADR-0019: Health-aware watering recommendation via baseline-versus-recent window comparison

## Status
Accepted

## Date
2026-06-26

## Deciders
- Justin Christenson (owner), who approved this during the Phase 5a kickoff.

---

## Context

ADR-0009 set the four-week gate and the descriptive median schedule. FOL-7 (added during Phase 4a) refined it: tie the recommended cadence to observed health rather than reporting the bare median. Establish a baseline cadence over the first four weeks, detect when it shifts, compare health around the shift, and recommend reverting to the cadence where the plant was healthier or adopting a newer cadence that coincided with better health, gated additionally on having health observations.

This has to work at 20 to 50 events per plant without overclaiming, and the copy stays correlational, never causal.

---

## Decision

The watering recommendation compares two windows: a baseline window (the first 28 days of history) and a recent window (the last 28 days). For each window it computes the median cadence (median gap between waterings in the window) and the median overall health of the observations in the window.

- If the two cadences are within a tolerance of `max(1, round(0.3 * baseline cadence))` days, or there is not yet enough disjoint history to compare (under eight weeks), or a window lacks the waterings or health observations needed to compare, it recommends the overall median of logged gaps (the median reminders fall back to when no manual override is set) and reports a `stable` basis.
- If the cadence shifted, it recommends the cadence of the window whose observations were healthier: the baseline cadence when recent health declined (a revert), the recent cadence when recent health held or improved (maintain).

Every recommendation carries the sample sizes (waterings used and health observations used), the baseline and recent cadences for transparency, and a plain-language, non-causal rationale ("health readings were higher, median 4 of 5 from 3 readings, when you watered about every 6 days"). The endpoint gates the recommendation with three states: `countdown` below the four-week mark, `no_health_data` when no observation carries overall health, and `ready` otherwise. The median amount is the median of the non-null logged amounts.

---

## Alternatives Considered

### Option A: Change-point detection over the full cadence series
Locate the shift statistically with a change-point algorithm, then compare health before and after.

**Rejected because**: change-point detection manufactures false shifts on 20 to 50 jittery points, presenting noise as a finding.

### Option B: Plain median only (ADR-0009 unchanged), ignoring health
Keep the bare median and drop the health awareness.

**Rejected because**: it drops FOL-7's intent of surfacing the cadence the plant was healthiest at.

### Option C: Per-observation rank correlation of cadence versus health to pick the cadence
Correlate cadence against health per plant and recommend the implied cadence.

**Rejected for the recommendation because**: it conflates with the correlation views and is noisy per plant. The two-window comparison is more robust and explainable. Rank correlation is still used for the pooled group views (ADR-0020).

---

## Pros

- Matches FOL-7's baseline-versus-shift framing directly.
- Robust and explainable; degrades to the plain median of logged gaps whenever the windows cannot be compared (which is the cadence reminders use when no override is set).
- Honest sample sizes and strictly non-causal copy.

---

## Cons

- A single tolerance knob decides "shifted versus stable"; it is a deliberate, conservative default.
- Two windows miss a shift that happened entirely in the middle of a long history, which is acceptable at this scale and revisited with data.

---

## Consequences

### Positive
- Defensible recommendations with a clear revert-or-maintain story grounded in observations.

### Negative
- The gate now has three states, which the contract and the eventual UI must carry.

### New Decisions Required
- The recommendation contract gains the gate state, the baseline and recent cadences, and the rationale (recorded in `decisions.md`).

---

## Influences

- ADR-0009 (the same small-sample honesty and the four-week gate).
- FOL-7's health-aware schedule design and the insight-language discipline in `CLAUDE.md` and spec section 8.

---

## Related Decisions

- ADR-0009 (this refines its descriptive median).
- ADR-0008 and ADR-0018 (the statistical method and where it runs).
- ADR-0020 (the correlation engine that shares this honesty).

---

## Review Date

Condition-based: revisit the window length and the tolerance once real per-plant data shows whether they catch the shifts that matter.
