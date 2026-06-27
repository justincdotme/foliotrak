# ADR-0018: Compute the recommendation and correlation engine in PHP, not MySQL window functions

## Status
Accepted

## Date
2026-06-26

## Deciders
- Justin Christenson (owner), who approved this during the Phase 5a kickoff.

---

## Context

ADR-0008 chose `markrogoyski/math-php` in PHP for the statistics but stated that "MySQL window functions perform the event-lag feature engineering." Phase 5a is the first phase to build that engine, so the wording now has to become code.

The test suite runs against sqlite `:memory:` (`phpunit.xml`), while production runs MySQL 8. Window-function feature engineering needs day deltas between a care action and later observations, which MySQL writes with `DATEDIFF` / `TIMESTAMPDIFF` / `DATE_ADD`, none of which exist in sqlite. A SQL implementation would therefore either run untested under the sqlite suite or require a second, sqlite-specific query path. The owner also wants the test environment to stay as close to the real app as practical.

---

## Decision

Compute the recommendation and correlation feature engineering in PHP over eager-loaded Eloquent collections, not in SQL window functions. Database access stays standard Eloquent (eager-loads, `where`, `orderBy`) with no engine-specific functions, so the same code path runs under the sqlite tests and MySQL production. This refines ADR-0008: the lag-window feature engineering it assigned to window functions is done in PHP, which is comfortable at the stated scale of 20 to 50 events per plant.

---

## Alternatives Considered

### Option A: SQL window functions (ADR-0008's original wording)
Push the feature engineering into MySQL with `OVER (...)` and date-diff functions.

**Rejected because**: it splits the test and production code paths on date-function dialect. The math that actually ships would run untested under sqlite, or a parallel sqlite query path would have to be maintained.

### Option B: A MySQL-backed test lane so the SQL is exercised
Keep SQL but add a MySQL connection to the test suite.

**Rejected because**: that is a larger test-infrastructure change than this phase warrants. It can be revisited if SQL pushdown is ever needed for performance.

---

## Pros

- One computation path, identically exercised by the tests and production.
- Trivial in-memory cost at 20 to 50 events per plant, with the per-plant per-type pulls index-covered.
- Keeps the test environment honest to production behavior.

---

## Cons

- In-memory computation would not scale to very large datasets, which is irrelevant at this scale.
- Departs from the spec's literal "MySQL window functions" wording.

---

## Consequences

### Positive
- Portable, fully tested statistical code with no second SQL dialect to maintain.

### Negative
- A future, much larger dataset wanting SQL pushdown for performance would reopen this.

### New Decisions Required
- None.

---

## Influences

- The sqlite-test versus MySQL-prod split recorded in `status.md`.
- The all-PHP computation convention already set by `CareScheduleResolver`, `CareDueResolver`, and `Trends`.

---

## Related Decisions

- ADR-0008 (this refines its feature-engineering mechanism).
- ADR-0001 (MySQL is still the store; only the analytics move to PHP).
- ADR-0019 and ADR-0020 (the engine this decision governs).

---

## Review Date

Condition-based: revisit if per-plant data volume grows enough to need SQL pushdown.
