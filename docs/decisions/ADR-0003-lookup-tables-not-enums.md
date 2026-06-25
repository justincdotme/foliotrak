# ADR-0003: Use seeded lookup tables instead of database ENUMs

## Status
Accepted

## Date
2026-06-24

## Deciders
- Justin Christenson (developer and owner), who proposed and decided this in the kickoff brainstorming session.

---

## Context

The domain has several enumerable sets that are expected to grow over the life of the app: care event types, fertilizer forms, nutrients, and symptoms. A MySQL ENUM column is the obvious quick choice, but extending an ENUM is a schema migration and an ALTER on the column. Symptoms and nutrients are also data the user-facing features query and group by, not only internal flags.

---

## Decision

Enumerable domain sets live in seeded lookup tables (`care_event_types`, `fertilizer_forms`, `nutrients`, `symptoms`), referenced by foreign key, not in MySQL ENUM columns. Seeders populate them, so adding a value is an insert rather than a migration. A few small, fixed, app-internal sets (plant `status`, observation `growth_rate`) still use Laravel Enum casts on plain varchar columns, because they are bounded behavioral states rather than user-extensible domain vocabulary.

---

## Alternatives Considered

### Option A: MySQL ENUM columns
Declare the enumerable sets as ENUM columns directly on the tables.

**Rejected because**: extending an ENUM requires an ALTER migration each time the domain grows, and ENUM ordering and altering are error-prone.

### Option B: Hard-coded application enums only
Keep the sets as PHP enums in application code with no database table.

**Rejected because**: symptoms and nutrients are data the insight and grouping features query and join against, so they belong in queryable tables.

---

## Pros

- The domain grows with a seeder insert, no migration (spec section 5).
- Sets are queryable and joinable, for example grouping observations by symptom or fertilizings by nutrient.
- Lookup rows carry metadata an ENUM cannot, such as label, sort order, symbol, and category.

---

## Cons

- Resolving labels needs a join rather than reading the column value directly.
- Seeders must stay authoritative and idempotent across environments.

---

## Consequences

### Positive
- New symptoms, nutrients, and event types ship without migrations.
- Richer per-value metadata is available to the UI and queries.

### Negative
- More reference tables and joins.
- Seeder discipline is required to keep reference data consistent.

### New Decisions Required
- None significant.

---

## Influences

- ADR-0002 (the spine's type column is a lookup foreign key).
- The reversible-migrations and seeded-lookups quality bar (section 10).
- The "lookup tables, not ENUMs" convention recorded in CLAUDE.md.

---

## Related Decisions

- ADR-0002 (the spine type column references one of these lookups).
- ADR-0001 (the relational store these tables live in).

---

## Review Date

No review date. This is a foundational choice unlikely to change.
