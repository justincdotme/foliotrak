# ADR-0028: The watering interval is learned from soil readings and the conditions they were taken in

## Status
Accepted

## Date
2026-08-22

## Deciders
- Justin Christenson (owner), who specified the behavior directly during the FOL-159 investigation.

---

## Context

ADR-0027 made the watering due date a projection: read the soil now, infer how fast it dries, and count forward to a threshold. On the owner's real data it did not work, in two separate ways.

**It almost never ran.** `SoilHistory::anchor()` only accepts a reading recorded more than five minutes after the last watering, because the watering modal writes its companion reading at the identical timestamp and that reading is taken before the water goes in. In this database, 36 of 71 moisture observations sit inside that window. The result was that **35 of 37 active plants got no projection at all** and silently fell back to the plain median gap, with nothing in the payload saying so. The feature was reported as delivered across several sessions while doing nothing for almost every plant.

**Where it did run, the rate was wrong.** `DryingRateEstimator::runs()` paired consecutive readings and discarded every pair that did not fall (`if ($perDay <= 0.0) continue;`). On a 1-to-10 scale that moves in whole points, most day-to-day deltas are zero, so the median of the surviving positive tail reports the steepest day as if it were every day. The Maidenhair Fern's real series was `0.5, 0, 1, 0, 0, -1, 0, 0, 0, 0, 1, 0, ...`; the estimator reported **1.0 points per day** against a true slope near **0.05**. Runs were also drawn from a list that merged hand-entered observations with calibrated sensor readings, so an observation of "wet" (8.0) next to a sensor reading of 5 manufactured a 3 points per day run. The Fern was watered on 2026-08-20 with logged gaps of 10, 12 and 23 days, and the app said water it again on 2026-08-23.

The owner described what the feature is for:

> If the watering frequency was, let's say, every 7 days, but when I just checked it 7 days later and the soil was moist, the system would use some algorithm to predict that 7 may be too often. If the soil was wet at 7 days, then the algorithm knew that it was definitely too early. It would track both of these measurements over time with the goal of more accurately proposing watering schedules. [...] We use these data points: temp, humidity, soil moisture, recorded watering days, and then let an algorithm adjust the proposed watering frequency over time.

That is a self-correcting interval, not a projection to a threshold.

---

## Decision

Every soil reading is treated as a verdict on the watering interval that preceded it. A reading of `value` taken `D` days after the previous watering implies an interval of

```
implied = D * (WET - WATER_AT) / (WET - value)
```

bounded to `[0.5 * D, 3 * D]`, so one reading can neither halve nor triple a schedule on its own. `WATER_AT = 3.0` and `WET = 7.0` on the existing scale, unchanged.

| Reading at day 7 | value | implied | reads as |
|---|---|---|---|
| dry | 2.0 | 5.6 | already dry, tighten to about 6 days |
| at threshold | 3.0 | 7.0 | 7 days was exactly right |
| moist | 5.0 | 14.0 | not ready at 7, closer to 14 |
| wet | 8.0 | 21.0 | definitely too early |

Two filters decide which readings carry information:

- A reading taken **less than a day** after watering describes the watering, not the plant. The Fern had a "wet" observation logged 18 hours after a soak; extrapolating from it implied a 2 day interval.
- A reading **still above watering level on a day the plant would not have been watered anyway** agrees with the cadence without testing it. Averaging it in would drag the estimate toward the day it happened to be taken. A reading at or below watering level always counts, whenever it was taken.

This is where the companion observation stops being discarded. It is measured against the watering **before** the one it accompanies, which makes those 36 readings the richest evidence in the database rather than dead weight.

Humidity and temperature enter by banding the evidence, reusing the bands already defined for drying runs (humidity under 40, 40 to 60, over 60 percent; temperature under 18, 18 to 24, over 24 Celsius). The estimate falls through four tiers, each reporting its sample size:

| Tier | Basis | Gate |
|---|---|---|
| `conditioned` | Readings in today's humidity band and temperature band | 3 readings |
| `humidity_banded` | Readings in today's humidity band | 3 readings |
| `temperature_banded` | Readings in today's temperature band | 3 readings |
| `plant_soil` | All of the plant's informative readings | 1 reading |

The learned figure is blended with the logged cadence rather than replacing it:

```
weight   = min(0.6, readings / 10)
interval = round(cadence * (1 - weight) + learned * weight)
```

so a first reading nudges and a long run of agreeing readings moves the schedule most of the way, but never all of it. A manual override still fixes the interval outright.

A reading logged **since** the last watering additionally moves this cycle's date, carried forward at the pace the learned interval implies and clamped to

```
[ lastWatering + max(1, round(interval / 2)),  lastWatering + 2 * interval ]
```

That floor is what structurally prevents the reported failure: a plant on a twelve day interval can never be told to water two days after it was watered. The ceiling is unchanged from ADR-0027 and still stops a stuck sensor from silencing a plant. An override fixes the interval but does not disable this correction; a wet reading defers the date either way.

`DryingRateEstimator` keeps `runs()`, now measuring each stretch end to end, partitioned by source, and broken by a watering or by a rise in moisture. It no longer estimates an interval; that is `WateringIntervalEstimator`'s job. Its tiering, `estimate()` and the `DryingRate` value object are removed, because leaving a second rate estimator in the tree recreates the "two watering algorithms that never met" problem ADR-0027 was written to end. The runs still feed `DryingRateHumidityFactor` and `DryingRateTemperatureFactor`, which is what they are now for.

Every due entry carries a `basis` object that is never null: the tier, the sample size, the logged cadence, what the readings alone implied, a plain-language rationale, and the reading that moved this cycle when there is one. Nothing falls back silently.

### On real data at adoption

36 of 37 active plants report a basis; the one that does not has no schedule at all, being below the four-week gate. 17 plants have soil evidence moving their interval, against 2 that got any projection before. The Fern moved from "due in 2 days" to a 17 day interval on an 11 day cadence, from 3 readings, and the intervals move both ways: one plant tightened from 8 days to 7 on dry readings, another loosened from 19 to 25 on wet ones.

---

## Alternatives Considered

### Option A: Keep the projection, fix the rate estimator
Partition runs by source and replace the positive-tail median with an unbiased slope.

**Rejected because**: it fixes the arithmetic and leaves the coverage problem untouched. The projection would still never run for the 35 plants whose only readings are stamped at a watering.

### Option B: A probability curve over the plant's interval distribution
Report the chance the plant needs water on each of the next N days, from the empirical distribution of its own gaps.

**Rejected because**: the owner's description is about correcting a proposed frequency, not about expressing a distribution. At three or four logged intervals per plant the curve would be almost entirely an artifact of the smoothing, and it would still need this estimator underneath it to answer what the interval is.

### Option C: Derive calibration anchors from each probe's observed range
The Fern's probe read 4 to 6 on a scale of 10 because its saved anchors are the theoretical 12-bit envelope, which put `WATER_AT` permanently out of reach from above.

**Rejected for now**: it reverses ADR-0023 through ADR-0026's decision that hardware-derived defaults never drift with reading history, and the owner has detached that probe as inaccurate. No moisture code is removed; the path stays wired and tested for when probes return.

---

## Pros

- Reaches every plant with a schedule instead of 2 in 37, and says out loud what each date rests on.
- Uses all four signals the owner named: logged watering days, soil moisture, humidity and temperature.
- Corrects in both directions, and the correction strengthens as readings accumulate rather than being fixed.
- The bounds are structural rather than advisory: no single reading, however wrong, can produce the reported failure.

## Cons

- The interval now depends on subjective observations, so a mis-logged "wet" moves the schedule. The 0.6 weight ceiling and the `[0.5D, 3D]` bound limit how far.
- Three readings per band is a thin gate. It is the smallest number at which a median is not just the middle of two.
- Sensor-sourced evidence still needs a well-calibrated probe, which is an open problem (Option C).

---

## Consequences

### Positive
- A logged observation changes what the app and the phone tell you, for every plant, which is what makes logging worth doing.

### Negative
- The due entry contract changes: `moisture` is replaced by `basis`, which is never null. Every fixture carrying `due_for_care` had to be migrated.

### New Decisions Required
- Whether to derive moisture calibration from observed range (Option C), once probes are back in use.

---

## Influences

- ADR-0008 (non-parametric statistics, medians over fitted models at this sample size), unchanged.
- ADR-0009 (four-week gate, sample size everywhere, strictly non-causal copy), unchanged.
- ADR-0027, whose thresholds, bands and clamp shape this keeps.

## Related Decisions

- Supersedes ADR-0027.
- ADR-0019's soil-adjustment clause stays superseded; the rest of ADR-0019 stands.
- ADR-0020 (the factor registry the drying-rate factors plug into).

---

## Review Date

Condition-based: revisit the three-reading band gates and the 0.6 weight ceiling once several plants have accumulated enough readings to reach the conditioned tier.
