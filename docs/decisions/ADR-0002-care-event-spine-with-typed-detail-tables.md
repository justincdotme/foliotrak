# ADR-0002: Model care data as a timeline spine with typed detail tables

## Status
Accepted

## Date
2026-06-24

## Deciders
- Justin Christenson (developer and owner), who proposed and decided this in the kickoff brainstorming session.

---

## Context

With MySQL chosen as the store (ADR-0001), the care data still had to be shaped. Every dated action (watering, fertilizing, repotting, observation) needs to sit on one sorted timeline so correlation and timeline queries are simple, yet each type carries different structured fields. Three shapes were genuinely in contention.

---

## Decision

A thin `care_events` spine table carries every dated action (plant, event type, `occurred_at`, note, and an audit stamp), with a 1:1 typed detail table per event type (`watering_details`, `fertilizing_details`, `repotting_details`, `observations`) plus child tables for variable-cardinality data (nutrients, symptoms). A single "Log Care" interaction can write more than one event at the same timestamp, each as its own spine row, keeping every type's timeline clean.

---

## Alternatives Considered

### Option A: Single JSON-payload events table
One `care_events` table with a JSON column holding each event's type-specific fields.

**Rejected because**: it forfeits typed columns, validation, foreign keys, and per-field indexes. Correlation queries would parse JSON instead of reading real columns.

### Option B: Fully separate per-type tables with no shared spine
A standalone table per event type and no common timeline.

**Rejected because**: there is no single sorted table to drive timeline and correlation queries, so every cross-type read becomes a UNION.

---

## Pros

- One indexed, sorted spine (`(plant_id, occurred_at)` and related indexes) drives timeline and correlation queries cleanly.
- Typed detail columns give validation, foreign keys, and indexes.
- Adding an event type is a new lookup row plus a detail table, not a reshaping of existing rows.
- Multiple events per timestamp keep each type's timeline distinct (a watering and an observation logged together stay separate rows).

---

## Cons

- Reading a full event is a join between spine and detail.
- More tables than a single JSON table, and more migrations to author.

---

## Consequences

### Positive
- Clean temporal queries, and window-function feature engineering reads real columns.
- Per-type FormRequests stay simple, one typed endpoint each (spec section 6).

### Negative
- Every event read joins spine to detail.
- Detail tables proliferate as event types grow.

### New Decisions Required
- How event types and other enumerable sets are represented, resolved by ADR-0003.

---

## Influences

- ADR-0001 (MySQL chosen as the relational store).
- The correlation engine's need for a single sorted timeline (section 8).
- The "spine plus typed detail tables" convention recorded in CLAUDE.md.

---

## Related Decisions

- ADR-0001 (the relational store this model builds on).
- ADR-0003 (lookup tables for the spine's type column and other enumerable sets).

---

## Review Date

No review date. This is a foundational data-model choice unlikely to change.
