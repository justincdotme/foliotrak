# ADR-0007: Use Recharts for charts and Nivo for the two heatmaps

## Status
Accepted

## Date
2026-06-24

## Deciders
- Justin Christenson (developer and owner), who proposed and decided this in the kickoff brainstorming session.

---

## Context

The visualization layer needs timeline overlays, trend lines, scatter and correlation plots, and group overlays, plus two heatmap types: a correlation heatmap and a GitHub-style calendar activity heatmap. A single charting library would be simplest, but Recharts does not produce either heatmap well.

---

## Decision

Recharts is the primary chart library, covering timelines, trend lines, scatter and correlation plots, and group overlays. Nivo fills the two heatmap types Recharts cannot do (the correlation heatmap and the calendar activity heatmap), imported per-chart to keep the bundle small.

---

## Alternatives Considered

### Option A: Recharts only
Standardize on Recharts for everything.

**Rejected because**: it cannot produce the correlation heatmap or the calendar activity heatmap.

### Option B: Nivo only
Standardize on Nivo for every chart.

**Rejected because**: Recharts covers the common responsive cases with less code and is the better fit for the bulk of charts, so making everything Nivo is heavier than needed.

### Option C: A single heavyweight library (ECharts or direct D3)
Use one large library or build directly on D3.

**Rejected because**: it is more complexity than two focused React-native libraries.

---

## Pros

- Recharts handles the common cases responsively with minimal code (spec section 9).
- Nivo covers exactly the two heatmaps that are otherwise impractical.
- Per-chart Nivo imports keep the bundle size in check.

---

## Cons

- Two charting libraries to learn and keep updated.
- Visual consistency across two libraries needs deliberate theming.

---

## Consequences

### Positive
- Every required chart type is achievable, and the bundle stays controlled.

### Negative
- Two dependencies to maintain.
- Theming effort to keep a consistent look across both libraries.

### New Decisions Required
- None.

---

## Influences

- The enumerated chart list and the two heatmap requirements (sections 8 and 9).
- The "Recharts plus Nivo" convention recorded in CLAUDE.md.

---

## Related Decisions

- ADR-0008 (the correlation results these charts render).

---

## Review Date

Condition-based: revisit if Recharts gains heatmap support or the bundle cost of two libraries becomes a problem.
