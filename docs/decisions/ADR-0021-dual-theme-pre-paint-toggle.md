# ADR-0021: Dual first-class themes with a persisted, applied-before-paint toggle

## Status
Accepted

## Date
2026-06-26

## Deciders
- Justin Christenson (developer and owner).

---

## Context

The Foliotrak UI needs a theming approach before Phase 0.5 components can be built. Two questions had to be settled together: how many themes to support, and when to apply the active theme relative to the browser's first paint.

Both light and dark are required as first-class outputs, not as afterthoughts. A plant-care tracker is used throughout the day; a dark theme is not optional for nighttime use. This made a single-theme build a non-starter.

The SPA boots client-side from a static shell. Any theme preference fetched from the server or applied after JavaScript hydration creates a flash: the browser paints the default theme, then corrects it once the preference is known. On a LAN-first install this round-trip is short but still visible, and the flash is jarring enough to matter.

A user manual override over the system preference is also required. Respecting `prefers-color-scheme` satisfies most users at first load, but a user may want to lock the app to one theme regardless of OS setting.

---

## Decision

Light and dark are both first-class themes. The implementation uses a `class="dark"` strategy on the document root with CSS-variable design tokens. All components are authored against semantic tokens (`--color-surface`, `--color-text`, etc.) so the active theme propagates through the variable cascade with no per-component branching.

The toggle defaults to the operating system color-scheme preference (`prefers-color-scheme`). Users can override to a fixed light or dark choice. That choice is written to `localStorage` and read by an inline script in the HTML `<head>` so the correct class is set on the root before any paint occurs. There is no flash of the wrong theme.

The toggle is exposed in two places: a quick control in the app-shell header and a setting in the Settings screen (see information-architecture.md).

---

## Alternatives Considered

### Option A: Single theme only
Ship one theme and omit the toggle.

**Rejected because**: both themes are first-class requirements. Dark mode is not a nice-to-have for a tracker used at any hour.

### Option B: Server-stored preference
Persist the theme choice in the user record and serve it from the API.

**Rejected because**: the SPA shell loads before any API call, so the preference arrives too late to prevent a flash. `localStorage` is available synchronously in the `<head>`, making it the right place for a pre-paint toggle.

### Option C: Apply theme after hydration and accept the flash
Read the preference from `localStorage` inside a React effect and apply the class after hydration.

**Rejected because**: a React effect runs after the browser has already painted. The flash is visible and unacceptable.

---

## Pros

- No flash of the wrong theme on load; the correct class is set before first paint.
- Per-component theme logic is eliminated; adding a new component only requires using semantic tokens.
- Adding a third theme in the future is a new CSS-variable block, not a code change across every component.
- System preference is honored automatically, so users who never touch the toggle get the right theme.

---

## Cons

- The inline `<head>` script must be small, synchronous, and free of framework dependencies; it runs before React loads.
- `localStorage` can become stale if the browser clears storage or the user moves to a new device; the fallback to `prefers-color-scheme` covers this.
- Two token sets (light and dark) must be maintained in parallel.

---

## Consequences

### Positive
- The design-system phase (Phase 0.5) produces a finished themed component set that all later phases consume without revisiting theming.
- Components stay simple: one markup tree, semantic token names, no conditional class logic for theme variants.

### Negative
- The HTML shell's `<head>` contains a small blocking script; this is intentional and necessary, but it diverges from a fully declarative template.
- Both token sets must be kept in sync as new design tokens are introduced.

### New Decisions Required
- None.

---

## Influences

- D18 (both themes with a user-facing toggle, persisted, applied before first paint, semantic tokens).
- Phase 0.5 roadmap deliverable: the theme toggle and both token sets are required before feature phases begin, so later phases build on a finished visual system.
- The LAN-first, self-hosted constraint, which rules out CDN-served theme scripts and makes a simple `localStorage` inline script preferable over any third-party theming service.

---

## Related Decisions

- [ADR-0005: Sanctum SPA cookie auth](./ADR-0005-sanctum-spa-cookie-auth.md): the SPA shell constraint that makes server-round-trip theme delivery impractical before first paint.

---

## Review Date

No review date. The `class="dark"` plus CSS-variable approach is a foundational styling choice; only a full design-system replacement would revisit it.
