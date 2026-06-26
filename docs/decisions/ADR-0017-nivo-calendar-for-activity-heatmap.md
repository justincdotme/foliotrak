# ADR-0017: Use Nivo @nivo/calendar for the plant activity heatmap

## Status
Accepted

## Date
2026-06-26

## Deciders
- Justin Christenson (developer and owner), who made the call and set the 60-day window.
- Claude Code, which surfaced the tradeoff during the Phase 3b brainstorming.

---

## Context

ADR-0007 settled the charting split: Recharts for most charts, Nivo for the two heatmaps Recharts cannot do, one of which is the calendar activity heatmap. When Phase 0.5 ported the prototype, the activity heatmap on plant detail shipped as a hand-rolled CSS-grid (a rolling 16-week contribution grid built from `div` cells), and Nivo was never added to the project.

Phase 3b (visualization UI) is where the heatmap gets wired to live care-event data, which forces the choice that the port deferred: adopt the Nivo calendar ADR-0007 called for, or keep the hand-rolled grid and deviate from ADR-0007. The hand-rolled grid worked and was already themed against the design tokens, so keeping it was a real option, not a strawman.

One misconception was corrected while deciding: Nivo's calendar does not require months of data before it renders. It draws whatever `from`/`to` window it is given, and days with no events paint as the empty color. The "mostly empty calendar" worry only applies to its textbook full-year usage, not to a bounded window.

---

## Decision

Adopt Nivo's `@nivo/calendar` (`ResponsiveTimeRange`) for the plant-detail activity heatmap, over a rolling 60-day window (today minus 60 days through today), themed to the design tokens (empty days read as `surface`, a low-to-high ramp toward `primary`), and imported per-chart as ADR-0007 requires. `ResponsiveTimeRange` is the right component here, not `ResponsiveCalendar`: the latter is year-locked (it always draws complete calendar years, leaving a 60-day window stranded in a mostly empty year), while `TimeRange` draws a contiguous from-to strip.

In practice: add `@nivo/calendar` and `@nivo/core` at 0.99.0 (React 19 is a supported peer). A pure helper aggregates care events into per-day counts and feeds Nivo's `[{ day: 'YYYY-MM-DD', value }]` shape; that helper is unit-tested while the Nivo render itself is not (consistent with the repo's chart-testing approach).

---

## Alternatives Considered

### Option A: Keep the hand-rolled CSS-grid heatmap
Wire the ported prototype's rolling 16-week contribution grid to live care events, fix its window to real time, and add no dependency.

**Rejected because**: it deviates from the settled ADR-0007 split, and it leaves us maintaining a bespoke chart whose month labels, per-day tooltips, legend, and quantized color scale we would hand-build and theme ourselves. Nivo provides those, and the per-chart import keeps the dependency cost contained.

### Option B: Full-year Nivo calendar (Nivo's default usage)
Render a full calendar year via `from`/`to` spanning the year.

**Rejected because**: a household tracker's plant typically has weeks to a few months of history, so a full year is mostly empty cells and is harder to read in mobile landscape. The 60-day rolling window keeps the heatmap dense and legible while still showing recent rhythm.

### Option C: A Recharts-only custom heatmap
Build the heatmap with Recharts primitives to avoid a second library.

**Rejected because**: Recharts has no calendar or heatmap, so this is the hand-rolled path (Option A) under another name, and ADR-0007 already rejected forcing every chart through Recharts.

---

## Pros

- Honors ADR-0007 and removes a bespoke chart from our maintenance surface.
- Built-in month labels, per-day tooltips, legend, and a themeable quantized color scale, rather than hand-rolled equivalents.
- The 60-day window stays dense and legible for the small histories typical here, including mobile landscape (charts are validated there per the design brief).
- React 19 is a supported peer at 0.99.0, so no install workarounds.

---

## Cons

- Adds two runtime dependencies (`@nivo/calendar` and `@nivo/core`) plus their Nivo transitive packages.
- Nivo lays the calendar out on month structure (month blocks with gaps), which is less tailored to an arbitrary "last N days" than the hand-rolled grid was.
- Theming Nivo to the CSS-variable tokens takes care: the color scale and the label/tooltip theme must be set, and CSS `var()` fills inside Nivo's SVG must be verified to resolve in both themes.

---

## Consequences

### Positive
- This is the project's first real Nivo usage, establishing the per-chart Nivo import and theming pattern that Phase 5b's correlation heatmap (also Nivo) will reuse.
- The events-to-daily-counts transform becomes a tested, reusable helper.

### Negative
- The bundle grows by the Nivo calendar packages, and the two-library maintenance cost ADR-0007 anticipated is now actually incurred.

### New Decisions Required
- Phase 5b's Nivo correlation heatmap will reuse this theming and import pattern; confirm it there.
- If a dashboard activity heatmap is ever added, decide whether it adopts the same Nivo calendar once its data source exists (the current `GET /api/dashboard` contract carries no per-day series).

---

## Influences

- ADR-0007, which designated the calendar activity heatmap to Nivo.
- The design-brief data-visualization rules: a sequential ramp from `surface` to `primary`, empty days read as `surface`, and charts legible in mobile landscape.
- The owner's call to default the window to 60 days, and the correction that Nivo renders any bounded window without needing months of data.
- The "Recharts plus Nivo" convention in CLAUDE.md.

---

## Related Decisions

- [ADR-0007: Use Recharts for charts and Nivo for the two heatmaps](./ADR-0007-recharts-plus-nivo-for-charts.md). This concretizes ADR-0007 for the activity heatmap.
- Recorded as D44 in `docs/project/decisions.md` (the build-log entry for this choice).
- Future ADR or decision: Phase 5b Nivo correlation heatmap, which follows this pattern.

---

## Review Date

Condition-based: revisit if the month-block layout proves awkward for the 60-day window in practice, if the Nivo bundle cost becomes a concern, or if Recharts gains a native calendar heatmap.
