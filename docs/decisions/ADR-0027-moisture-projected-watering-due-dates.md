# ADR-0027: Watering due dates projected from soil moisture and an inferred drying rate

## Status
Accepted

## Date
2026-08-20

## Deciders
- Justin Christenson (owner), who approved this during the FOL-158 design pass.

---

## Context

Two watering algorithms existed and never met.

The due date came from `CareSchedule::due()`: the last watering plus the interval, where the interval is the manual override or the gated median gap. It read no soil moisture, no humidity, no temperature. Nothing the owner observed could move it except logging another watering. That produced the reported failure: a plant read "Water today" on a day an observation recorded the soil as still wet.

The recommendation engine did read soil moisture. ADR-0019 and D65 (FOL-47) had `WateringScheduleRecommender::withSoilAdjustment()` scale the recommended cadence by at most 20 percent, which for a "wet" reading (8.0 on the 1-to-10 scale) worked out to 10 percent. That number lived on the Recommended tab behind a "Use this for my schedule" button and never reached the due date or the Pushover reminder. It also averaged the last three readings with no age limit, so a three-month-old reading counted as heavily as today's, and it read manual observations only, so calibrated moisture sensors never reached it.

The owner's requirement was that a wet reading definitely stops the plant being due today, that the resulting date be computed rather than looked up, and that the influence of humidity and temperature be inferred from observed data and improve as data accumulates rather than being fixed coefficients.

---

## Decision

The watering due date is the projected crossing of a needs-water threshold on the existing 1-to-10 soil scale (1 driest, 10 wettest), reusing the thresholds the soil adjustment already used: `WATER_AT = 3.0`, `WET = 7.0`.

```
due_date = anchor.at + (anchor.value - WATER_AT) / drying_rate
```

The anchor is the newest soil reading recorded more than five minutes after the most recent watering, drawn from manual observations or from moisture sensors calibrated through `MoistureCalibration::scale()`. Readings at or before the last watering are stale, because the watering reset the soil. The five-minute tolerance exists because the watering modal writes a companion observation at the identical `occurred_at`, and that reading is taken before the water goes in; treating it as post-watering moisture would invert the inferred rate.

The drying rate is inferred from the plant's own history. Consecutive readings with no watering between them form a drying run, yielding a rate in scale points per day plus the mean ambient humidity and temperature over the run. The estimate falls through four tiers, each reporting its sample size:

| Tier | Basis | Gate |
|---|---|---|
| `conditioned` | Median rate of runs in the same humidity band and temperature band as current conditions | 5 runs |
| `humidity_banded` | Median rate of runs in the matching humidity band alone | 5 runs |
| `plant_median` | Median of all the plant's observed rates | 3 runs |
| `cadence_baseline` | `(WET - WATER_AT) / cadence`, one cadence assumed to span one wet-to-dry swing | always |

Bands are coarse and non-parametric: humidity under 40, 40 to 60, over 60 percent; temperature under 18, 18 to 24, over 24 Celsius. Medians throughout, never a fitted curve, consistent with ADR-0008 at these sample sizes. A plant graduates upward through the tiers as its own history accumulates, which is how the estimate improves with data.

Sensor readings are collapsed to one median value per calendar day before run extraction, because raw readings are 15 to 30 minutes apart and would fall below the half-day minimum run length, yielding no runs at all. The anchor still uses the newest raw reading. History is bounded at 90 days of observations and 30 days of sensor readings.

The projected date is clamped to `[scheduleAnchor + 1 day, scheduleAnchor + 2 * cadence]` so a stuck sensor can neither silence a plant nor nag about one. The adjustment is symmetric: a wet reading pushes the date out, a dry reading pulls it in.

Because `CareDue::for()` is the single entry point for the plants list, the timeline, the dashboard, `Plant::isLikelyDry()`, and `SendCareReminders`, every surface inherits the adjusted date without changes of its own. A plant with no usable anchor gets no projection and keeps the previous behavior exactly.

`withSoilAdjustment()` is removed. With the projection owning the due date it would apply the same signal a second time to the cadence. The cadence recommendation returns to answering how often the plant has been healthiest; the due date answers when the soil will actually be dry.

The inferred relationship is surfaced rather than hidden: `AmbientTemperatureFactor` fills the last enumerated numeric gap, and `DryingRateHumidityFactor` and `DryingRateTemperatureFactor` publish the humidity-versus-drying and temperature-versus-drying relationships through the existing Spearman and Benjamini-Hochberg machinery, with sample size and confidence band.

---

## Alternatives Considered

### Option A: A fixed deferral table
Wet defers a set number of days, moist fewer, dry none.

**Rejected because**: it cannot learn, and it ignores the humidity and temperature influence the owner specifically asked for.

### Option B: Recompute the cadence only, and wire that into the due date
Strengthen `withSoilAdjustment()`, drop its 20 percent cap, and let the cadence drive the date.

**Rejected because**: one reading barely moves a median. A plant on a four-day cadence would still read "Water today" after a wet reading, which is the exact failure being fixed.

### Option C: Adjust the display, leave reminders on the raw cadence
Cheaper, and no query-load risk on the reminder run.

**Rejected because**: the app and the phone would disagree about whether a plant needs water.

---

## Pros

- Fixes the reported failure directly, and symmetrically.
- The rate is measured from the plant's own behavior rather than assumed, and demonstrably improves with data: on real data at adoption, one sensor-equipped plant already reached the `conditioned` tier with six runs.
- One source of truth, satisfied structurally rather than by convention.
- Degrades to the previous behavior exactly when a plant has no moisture data.
- Adding a factor stays a one-class change (ADR-0020).

---

## Cons

- More moving parts on the hottest read path, which is why the projection inputs are eager loaded and the windows bounded.
- The band edges and the two sample gates are judgment calls, chosen conservatively.
- Sensor-sourced runs need up to 30 days of history before the conditioned tier can fire for a newly added sensor.

---

## Consequences

### Positive
- A recorded observation now changes what the app and the phone tell you, which is what makes logging worth doing.

### Negative
- The due entry contract grows a nested `moisture` object, and every fixture carrying `due_for_care` had to gain the key.

### New Decisions Required
- None. This supersedes the soil-adjustment half of ADR-0019 and D65; the rest of ADR-0019 stands.

---

## Influences

- ADR-0008 (non-parametric statistics, medians over fitted models at this sample size), unchanged.
- ADR-0009 (four-week gate, sample size everywhere, strictly non-causal copy), unchanged.
- The sensor calibration work in ADR-0023 through ADR-0026, which made a sensor reading comparable to a hand-entered one.

---

## Related Decisions

- ADR-0019 (this supersedes its soil-adjustment clause).
- ADR-0020 (the factor registry the three new factors plug into).
- ADR-0009 (the honesty discipline this inherits).

---

## Review Date

Condition-based: revisit the band edges and the two sample gates once enough per-plant drying data exists to show whether they fire when they should.
