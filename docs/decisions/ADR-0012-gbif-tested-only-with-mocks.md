# ADR-0012: Test the GBIF integration only against mocks

## Status
Accepted

## Date
2026-06-24

## Deciders
- Justin Christenson (developer and owner), who set this constraint while scoping the Phase 1a GBIF integration.

---

## Context

The GBIF client (ADR-0011) is the one place the app reaches a third party that takes no API key, offers no SLA, and throttles by IP at its discretion. A test suite runs constantly: in CI, in pre-commit loops, and in a failing test that gets re-run on a loop. If any of those reaches the live GBIF endpoint, an ordinary red-test cycle becomes a burst of unwanted traffic from the household's single public IP, the precise thing ADR-0011 exists to prevent. The risk is asymmetric: one misconfigured test on a loop can get the IP throttled or blocked, breaking species suggest for the whole household.

---

## Decision

Every test that touches the GBIF client uses faked HTTP responses and never opens a socket to GBIF. Use Laravel's `Http::fake()` with a recorded sample suggest payload as a fixture, so the client, the cache-write path, the throttle and backoff behavior, and the `/api/species/suggest` endpoint are all exercised against deterministic canned responses.

No test, in CI or locally, may hit `api.gbif.org`. The HTTP fake is installed in the setup for the species-suggest suite, and "no live GBIF in tests" is a documented rule: a test that calls the real endpoint is a defect, not an option. Live verification against GBIF, if ever needed, is a deliberate manual step outside the automated suite, never wired into CI.

---

## Alternatives Considered

### Option A: Hit the live GBIF endpoint in integration tests
Let integration tests call the real API for fidelity.

**Rejected because**: it couples the suite to GBIF availability (flaky, no SLA) and turns every test loop into live traffic that risks the IP block ADR-0011 prevents. Determinism and safety both argue against it.

### Option B: A periodic live contract test
Call GBIF on a schedule (for example, nightly) to catch upstream shape changes.

**Rejected because**: it adds live traffic and infrastructure for a stable endpoint. If GBIF's suggest shape ever drifts, refreshing the fixture and a manual check cover it without standing live calls. Reconsider if drift becomes real.

---

## Pros

- The suite can run any number of times, in any loop, and never touch GBIF, so testing can never get the household IP blocked.
- Deterministic, fast, fully offline tests for the entire species-suggest path.

---

## Cons

- A recorded fixture can drift from GBIF's real response shape over time, and nothing automated will flag it; catching drift needs an occasional deliberate manual check.

---

## Consequences

### Positive
- ADR-0011's safety envelope holds under the workload that runs most often, the tests themselves.

### Negative
- Someone refreshes the recorded fixture if GBIF's suggest payload changes.

### New Decisions Required
- None now. A guarded, rate-limited live contract check could be reconsidered if response drift becomes frequent.

---

## Influences

- ADR-0011 (rate-limit-safe GBIF client): this is its test-side corollary; the thin owned client exists partly to be trivially fakeable.
- The single shared public IP and GBIF's discretionary, IP-level throttling.

---

## Related Decisions

- [ADR-0011: Roll our own rate-limit-safe, cache-first GBIF client](./ADR-0011-rate-limit-safe-gbif-client.md): the runtime decision this protects in the test suite.
- [ADR-0006: Use GBIF for species autocomplete, lazily cached locally](./ADR-0006-gbif-species-autocomplete-lazily-cached.md): the source decision both ADRs build on.

---

## Review Date

Condition-based: revisit if GBIF gains a sanctioned sandbox or test endpoint, or if response-shape drift becomes frequent enough to justify a guarded, rate-limited contract check outside the main suite.
