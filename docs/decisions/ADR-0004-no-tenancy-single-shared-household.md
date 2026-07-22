# ADR-0004: No tenancy, one household per install with all data shared

## Status
Accepted

## Date
2026-06-24

## Deciders
- Justin Christenson (developer and owner), who proposed and decided this in the kickoff brainstorming session.

---

## Context

Most multi-user web apps need per-user or per-tenant data isolation. Foliotrak is self-hosted, one install per household, and deployed on a home LAN. Every household member who logs in is meant to see and edit the same plants. The question was whether to build any tenancy or ownership boundary at all.

---

## Decision

There is no tenancy and no per-user data isolation. One install serves one household, and every authenticated user sees and edits all data. Authentication exists so the app is not wide open on the LAN, not to separate users from each other. No model carries a `household_id` or per-user ownership scope. `logged_by_user_id` is captured as an audit stamp only and is unused by features for now.

---

## Alternatives Considered

### Option A: Per-user ownership scoping
Each plant is owned by a user, and queries are scoped to the owner.

**Rejected because**: household members share plant care, so scoping would force awkward sharing logic for no benefit.

### Option B: Multi-household tenancy
Add a `household_id` to every table and isolate data per household.

**Rejected because**: it is speculative scope. One install per household is the deployment model, so cross-household isolation is solved by running separate installs.

---

## Pros

- Far simpler authorization: Policies require only an authenticated user, with no ownership checks (spec section 4).
- Matches reality, since a household shares its plants.
- No tenancy columns, scopes, or global query scopes to maintain.

---

## Cons

- Cannot host two unrelated households on one install, which means running two installs.
- No per-user privacy within a household, which is acceptable by design.

---

## Consequences

### Positive
- Authentication and authorization stay minimal.
- The data model carries no tenancy weight.

### Negative
- Per-person attribution and multi-household support would be future additions, though `logged_by_user_id` already softens per-person attribution to a feature add rather than a migration (section 12).

### New Decisions Required
- How authentication is implemented as a simple gate, resolved by ADR-0005.

---

## Influences

- The "one install per household" deployment model (section 1).
- The "no tenancy" convention recorded in CLAUDE.md.

---

## Related Decisions

- ADR-0005 (the authentication gate this decision relies on).

---

## Review Date

Condition-based: revisit only if hosting multiple unrelated households on one install becomes a goal. Unlikely.
