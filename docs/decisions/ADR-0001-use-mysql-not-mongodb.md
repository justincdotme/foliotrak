# ADR-0001: Use MySQL as the primary store, not MongoDB

## Status
Accepted

## Date
2026-06-24

## Deciders
- Justin Christenson (developer and owner), who proposed and decided this in the kickoff brainstorming session. Solo decision for a self-hosted, single-household project.

---

## Context

Foliotrak records heterogeneous care data per plant: watering amounts, fertilizing with brand, product, NPK, and dose plus a variable list of nutrients (an organic feed can link several), repotting with soil recipes and pot sizes, and observations with health, light, growth, weight, and a variable set of symptoms. Most fields are optional and the shape differs by event type, which makes a document store look attractive at first glance.

The app is self-hosted, LAN-first, and serves a single household, with realistic year-one volumes of roughly 20 to 50 events per plant. A primary store had to be chosen before the data model, migrations, and the insight engine could be built.

---

## Decision

MySQL 8 is the single primary store. The heterogeneity is modeled relationally through a care-event spine and typed detail tables (see ADR-0002). There is no document store and no polyglot persistence.

---

## Alternatives Considered

### Option A: MongoDB or another document store
Store each care event as a flexible document, letting optional and type-specific fields vary freely.

**Rejected because**: the heterogeneity is fully tractable relationally at this scale. A document store would forfeit typed columns, foreign keys, and the SQL window functions the insight engine uses for event-lag feature engineering, and migrating back later would cost effort with no offsetting gain.

### Option B: Polyglot persistence (MySQL plus a document store for events)
Keep relational data in MySQL but push variable care events into a document store.

**Rejected because**: running two stores is unjustified operational weight for a single-household app.

---

## Pros

- Typed columns, foreign keys, and indexes give validation and fast temporal queries the analytics depend on.
- MySQL 8 window functions perform the event-lag feature engineering for correlation views directly in the database (spec section 8).
- Laravel and Eloquent are first-class against MySQL, so migrations, seeders, and relationships stay idiomatic.
- One store to back up: the host bind-mounted data directory is the entire database.

---

## Cons

- Adding a field to a detail type needs a migration, where a document store would not.
- Variable-cardinality data such as nutrient lists and symptoms needs pivot or child tables rather than nested arrays.

---

## Consequences

### Positive
- Enables the spine-plus-detail model (ADR-0002) and window-function feature engineering.
- A single, portable, host bind-mounted database.

### Negative
- Schema evolution is migration-bound.
- Some shapes need extra join tables.

### New Decisions Required
- How to model the heterogeneity within MySQL, resolved by ADR-0002.

---

## Influences

- Self-hosted single-household scale (spec section 1).
- The insight engine's reliance on SQL window functions (section 8).
- The "MySQL, not MongoDB" convention recorded in CLAUDE.md.

---

## Related Decisions

- ADR-0002 (the spine-plus-detail data model that realizes this choice).
- ADR-0003 (lookup tables instead of ENUMs, built on this relational store).
- ADR-0008 (PHP statistics rely on SQL feature engineering in this store).

---

## Review Date

Condition-based: revisit only if the app outgrows single-household scale or the care data becomes genuinely schemaless. This is a foundational choice and unlikely to change.
