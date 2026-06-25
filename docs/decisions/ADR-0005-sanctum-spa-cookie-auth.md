# ADR-0005: Authenticate the SPA with Laravel Sanctum in cookie and session mode

## Status
Accepted

## Date
2026-06-24

## Deciders
- Justin Christenson (developer and owner), who proposed and decided this in the kickoff brainstorming session.

---

## Context

The React SPA and the Laravel API are served same-origin behind nginx, which serves the static bundle and reverse-proxies `/api` to php-fpm. Authentication is only a gate (ADR-0004), but it still has to be implemented. The real choice is between cookie and session auth and token-bearer auth that stores a token in JavaScript.

---

## Decision

Use Laravel Sanctum in SPA mode, which is stateful with session cookies plus CSRF. The SPA calls `/sanctum/csrf-cookie`, then `POST /login`, and Sanctum's stateful middleware authenticates subsequent `/api` calls by session cookie. No tokens are stored in JavaScript.

---

## Alternatives Considered

### Option A: Bearer tokens in JavaScript (Sanctum personal-access tokens or JWT)
Issue a token on login and store it in localStorage or memory, sending it as a bearer header.

**Rejected because**: a same-origin SPA and API make cookies the natural fit, and keeping tokens out of JavaScript removes an XSS token-theft vector.

### Option B: Full OAuth with Laravel Passport
Stand up an OAuth server for authentication.

**Rejected because**: it is heavy overkill for a single-household LAN app with no third-party clients.

---

## Pros

- Same-origin deployment means cookies work naturally with CSRF protection and no token storage in JavaScript (spec section 4).
- Bundled with Laravel, so the code is minimal and idiomatic.
- An HttpOnly session cookie is not readable by JavaScript, which reduces the XSS blast radius.

---

## Cons

- Requires the CSRF-cookie priming step and same-origin deployment, which is acceptable since it is the deployment model.
- Stateful sessions need server-side session storage, which is fine at single-household scale.

---

## Consequences

### Positive
- Login, logout, and `/api` protection are a thin layer, and Policies require only an authenticated user (ADR-0004).

### Negative
- A future native mobile or cross-origin client would need a token path added.

### New Decisions Required
- None for the current scope.

---

## Influences

- ADR-0004 (auth as a gate, not an isolation boundary).
- The same-origin nginx architecture (section 3).
- The "Sanctum SPA cookie mode" convention recorded in CLAUDE.md.

---

## Related Decisions

- ADR-0004 (the gate this decision implements).

---

## Review Date

Condition-based: revisit if a cross-origin or native client is added.
