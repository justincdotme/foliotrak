# Writing frontend tests in foliotrak

Practical guide for adding or changing tests under `resources/js/`.

## Two test styles coexist

**Colocated `*.test.tsx`** next to the component it covers (for example
`resources/js/pages/plants.test.tsx` next to `plants.tsx`). These
`vi.mock()` the hook or `@/api/client` module directly and assert on
component behavior given a mocked return value. Fast, and good for pinning
pure component logic (conditional rendering, derived display state, event
wiring) where the shape of the API response isn't the thing under test.

**MSW-backed integration tests** under `resources/js/test/integration/**`.
These use the real hooks and a real (mocked-at-the-network-layer) HTTP
round trip through MSW. This is the primary style going forward for
anything that talks to the API. MSW handlers are the contract layer: if the
real API's response shape drifts from a fixture, these tests should break.
The colocated style structurally cannot catch that, because the mock value
never has to match reality.

**Rule of thumb:** if the thing you're testing would still pass with a
made-up mock value substituted for the real API shape, colocated is fine.
If the point of the test is "does this component correctly handle what the
API actually sends back," write it under `test/integration/`.

## Adding a new MSW-backed test

1. **Find or add a fixture** under `resources/js/test/fixtures/`, grouped by
   resource (`plants/`, `care-events/`, `lookups/`, etc.). Prefer a real
   captured response over a hand-written one. See the capture workflow
   below. Reuse an existing fixture where the shape already fits; add a new
   one only when no existing fixture covers the case (e.g. an empty list, a
   validation error).
2. **Add or reuse a handler** in `resources/js/test/handlers/index.ts`. Each
   handler imports its fixture by name and returns it; don't inline
   response bodies. Most reads are a single `http.get(path, () =>
   HttpResponse.json(fixture))` line. No per-id branching exists in this
   codebase (unlike Tally's trigger-id convention), because error paths are
   handled per test with `server.use()` instead.
3. **Write the test** using a local `makeWrapper()` (or `Wrapper`, for page
   tests that also need a router) rather than a shared render helper. See
   `resources/js/test/integration/lib/api/usePlants.test.ts` for the
   pattern:

   ```ts
   const makeWrapper = () => {
     const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
     return function Wrapper({ children }: { children: React.ReactNode }) {
       return React.createElement(QueryClientProvider, { client: qc }, children)
     }
   }
   ```

   Name the returned component (`function Wrapper(...)`), never an
   anonymous arrow. See Conventions below.
4. **Override per-test behavior with `server.use(...)`** inside the `it()`
   block for error paths, edge cases, or asserting on the real request body:

   ```ts
   server.use(http.get('/api/plants', () => jsonMessage(500, 'boom')))
   ```

   This layers on top of the default handler for the duration of that test
   only; `afterEach` in `resources/js/test/setup.ts` resets it.

## Capturing a real fixture

Fixtures should reflect an actual response from the running app, not a
hand-typed approximation. To capture one:

1. Authenticate against the live app using the dedicated Claude test
   account (see project memory `foliotrak-test-account.md` for the
   credentials, and do not hardcode them here).
2. Every request in the handshake needs an `Origin` header matching the
   app's URL. Without it, Sanctum's `EnsureFrontendRequestsAreStateful`
   never treats the request as stateful: login returns 200, but the session
   cookie is silently never honored on subsequent calls. `curl` sends no
   `Origin`/`Referer` by default, so add it explicitly:
   `GET /sanctum/csrf-cookie`, then `POST /login`, then the authenticated
   request you actually want, reusing one cookie jar throughout.
3. Sanitize before committing: strip anything that shouldn't live in a
   fixture (real emails, tokens), but keep the real shape and field names.
4. **For any write capture** (POST/PATCH), clean up immediately by deleting
   the row or reverting the field you changed. This app has no tenancy; every
   row is shared, and a stray capture record can throw off dashboard or
   insights math for actual data.

## Conventions established across this effort

- **Name wrapper components.** `function Wrapper({ children }) { ... }`, not
  `({ children }) => ...`. Naming satisfies the `react/display-name` lint rule
  and makes wrapper components identifiable in the React DevTools during a
  failing-test debug session.
- **Use `?.[0]` when indexing a fixture array.** `noUncheckedIndexedAccess`
  is on in `tsconfig.app.json`; `fixture.data[0].id` won't type-check but
  `fixture.data[0]?.id` will.
- **Assert on the real intercepted request body for writes.** Don't stop at
  the resulting UI state after a mutation. Spy on the request via
  `server.use()` and assert the actual JSON/FormData body the component
  sent. That's what catches a field being silently dropped or renamed.
- **Stub only genuinely expensive or SVG-heavy components** (chart
  components built on Recharts/Nivo), never hooks. Stubbing a hook in an
  "integration" test defeats the point of the layer.

## Known quirks worth knowing (don't "fix" these in tests)

- **`GET /api/insights/locations` returns a bare array**, no `{ data: ... }`
  envelope. This is real production behavior, not a fixture mistake. The
  controller calls `response()->json($array)` directly instead of going
  through an API Resource.
- **The axios interceptor hard-redirects on 401 before React Query ever
  sees an error.** `resources/js/lib/api.ts`'s response interceptor sets
  `window.location.href = '/login'` and returns a promise that never
  resolves for any 401 outside `/login` or `/sanctum/*`. That means
  `AuthGate`'s own `isError` branch (`resources/js/components/shell/auth-gate.tsx`)
  is only reachable for non-401 failures (a 500 from `/api/user`, for
  example). A real 401 never reaches it because the interceptor has
  already navigated away.
- **`useCareEventMutations` has no `createRelocation`.** Relocations are a
  server-side side effect of `PATCH /api/plants/{id}` changing
  `location_id`, not a direct client-initiated mutation. `POST
  /api/plants/:id/relocations` exists as an MSW handler and fixture but has
  no real caller in the app (the only reference is the unused
  `resources/js/api/mock.ts`).

## Coverage

The matrix below maps all 38 MSW handlers in
`resources/js/test/handlers/index.ts` to their fixture(s) and the test
file(s) that exercise them, derived by reading the handler file and every
file under `resources/js/test/integration/`.

Note on file count: earlier planning documents for this effort cited 39 new
integration test files. The actual tree has **31**: 19 under `lib/api/`
(14 read hooks + 5 mutation-hook groups), 6 under `pages/`, and 6 under
`components/forms/`. 58 total test files minus 27 pre-existing colocated
tests confirms 31 is the correct count.

### Read handlers (19)

| Endpoint | Fixture(s) | Exercised by |
|---|---|---|
| `GET /sanctum/csrf-cookie` | none (204) | `pages/login.test.tsx` (login flow issues this first) |
| `GET /api/user` | `user.json` | `lib/api/useCurrentUser.test.ts`; `pages/settings.test.tsx`; `pages/login.test.tsx` (AuthGate 500 override) |
| `GET /api/plants` | `plants/list.json` (+ `plants/empty.json` via override) | `lib/api/usePlants.test.ts`; `pages/plants.test.tsx` |
| `GET /api/plants/:id` | `plants/detail-1.json` | `lib/api/usePlant.test.ts`; `pages/plant-detail.test.tsx` |
| `GET /api/plants/:id/timeline` | `plant/timeline-1.json` | `lib/api/useTimeline.test.ts`; `pages/plant-detail.test.tsx` |
| `GET /api/plants/:id/recommendations` | `plant/recommendations-1.json` | `lib/api/useRecommendations.test.ts`; `pages/plant-detail.test.tsx` |
| `GET /api/plants/:id/photos` | `plant/photos-1.json` | `lib/api/usePlantPhotos.test.ts`; `pages/plant-detail.test.tsx` |
| `GET /api/dashboard` | `dashboard/populated.json` | `lib/api/useDashboard.test.ts`; `pages/dashboard.test.tsx` |
| `GET /api/insights/group` | `insights/group.json` | `lib/api/useGroupInsights.test.ts`; `pages/insights.test.tsx` |
| `GET /api/insights/locations` | `insights/locations.json` | **Not exercised by any integration test.** `getLocationSummary` in `resources/js/api/client.ts` has no hook and no page caller; the only test that touches it is the colocated unit test `resources/js/api/client.test.ts` (mocked axios, not MSW). |
| `GET /api/care-event-types` | `lookups/care-event-types.json` | **Not exercised by any test, and not called by any app code.** No client function, hook, or component references this path outside the handler file itself. |
| `GET /api/fertilizer-forms` | `lookups/fertilizer-forms.json` | `lib/api/useCareLookups.test.ts`; `components/forms/log-fertilizing-form.test.tsx` |
| `GET /api/nutrients` | `lookups/nutrients.json` | `lib/api/useCareLookups.test.ts`; `components/forms/log-fertilizing-form.test.tsx` |
| `GET /api/symptoms` | `lookups/symptoms.json` | `lib/api/useCareLookups.test.ts`; `components/forms/log-observation-form.test.tsx` |
| `GET /api/equipment` | `lookups/equipment.json` | `lib/api/useEquipment.test.ts`; `pages/plant-detail.test.tsx` |
| `GET /api/locations` | `lookups/locations.json` | `lib/api/useLocations.test.ts`; `pages/insights.test.tsx`; `components/forms/relocation-edit-form.test.tsx`; `components/forms/add-plant-form.test.tsx` |
| `GET /api/tags` | `lookups/tags.json` | `lib/api/useTags.test.ts`; `pages/plants.test.tsx`; `pages/insights.test.tsx`; `pages/settings.test.tsx`; `pages/plant-detail.test.tsx`; `components/forms/add-plant-form.test.tsx` |
| `GET /api/settings` | `settings.json` | `lib/api/useSettings.test.ts`; `pages/settings.test.tsx`; `components/forms/log-observation-form.test.tsx` (temperature unit) |
| `GET /api/species/suggest` | `species/suggest.json` | `lib/api/useSpeciesSuggest.test.ts`; `components/forms/add-plant-form.test.tsx` (debounced species search) |

### Write handlers (19)

| Endpoint | Fixture(s) | Exercised by |
|---|---|---|
| `POST /login` | `auth/login-200.json`, `auth/login-401.json` | `pages/login.test.tsx` (both branches) |
| `POST /logout` | `auth/logout-200.json` | **Not exercised by any integration test.** The only real caller is `resources/js/components/shell/shell.tsx`; no integration test renders the shell, and `pages/settings.test.tsx` passes a mocked `onLogout`. |
| `POST /api/plants` | `plants/created-201.json` | `lib/api/usePlantMutations.test.ts`; `components/forms/add-plant-form.test.tsx` |
| `PATCH /api/plants/:id` | `plants/detail-1.json` | `lib/api/usePlantMutations.test.ts` (`useUpdatePlant`, `useSetCoverPhoto`) |
| `DELETE /api/plants/:id` | none (204) | **Not exercised, and has no real caller.** The only `deletePlant` implementation is in the unused `resources/js/api/mock.ts`. |
| `POST /api/plants/:id/photos` | `photos/created-201.json` | `lib/api/usePlantMutations.test.ts`; `lib/api/useCareEventMutations.test.ts`; `components/forms/log-observation-form.test.tsx` |
| `DELETE /api/photos/:id` | none (204) | `lib/api/usePlantMutations.test.ts` (`useDeletePhoto`) |
| `POST /api/plants/:id/waterings` | `care-events/watering.json` | `lib/api/useCareEventMutations.test.ts`; `components/forms/log-watering-form.test.tsx` (including a 422 override) |
| `POST /api/plants/:id/fertilizings` | `care-events/fertilizing.json` | `lib/api/useCareEventMutations.test.ts`; `components/forms/log-fertilizing-form.test.tsx` |
| `POST /api/plants/:id/repottings` | `care-events/repotting.json` | `lib/api/useCareEventMutations.test.ts`; `components/forms/log-repotting-form.test.tsx` |
| `POST /api/plants/:id/observations` | `care-events/observation.json` | `lib/api/useCareEventMutations.test.ts`; `components/forms/log-observation-form.test.tsx` |
| `POST /api/plants/:id/relocations` | `care-events/relocation.json` | **Not exercised, and has no real caller.** `useCareEventMutations` has no `createRelocation`; `RelocationEditForm` only issues a PATCH. The only `createRelocation` implementation is the unused `resources/js/api/mock.ts`. |
| `PATCH /api/care-events/:id` | `care-events/updated.json` | `lib/api/useCareEventMutations.test.ts`; all 6 form tests (edit paths); `pages/plant-detail.test.tsx` |
| `DELETE /api/care-events/:id` | none (204) | `lib/api/useCareEventMutations.test.ts`; `pages/plant-detail.test.tsx` (delete-timeline-event) |
| `POST /api/locations` | `locations/created-201.json` | `lib/api/useLocations-mutations.test.ts` |
| `POST /api/tags` | `tags/created-201.json` | `lib/api/useTags-mutations.test.ts`; `pages/settings.test.tsx` |
| `PATCH /api/tags/:id` | `tags/updated.json` | `lib/api/useTags-mutations.test.ts` |
| `DELETE /api/tags/:id` | none (204) | `lib/api/useTags-mutations.test.ts` |
| `PATCH /api/settings` | `settings/updated.json` | `lib/api/useSettings-mutations.test.ts`; `pages/settings.test.tsx` |

### Real gaps

Five of the 38 handlers have a fixture and a handler but no exercising
integration test:

- `GET /api/care-event-types`, `POST /api/plants/:id/relocations`,
  `DELETE /api/plants/:id`, and `GET /api/insights/locations` are dead
  handlers. Nothing in the shipped app calls these paths. Confirmed by
  direct grep: `getLocationSummary` in `resources/js/api/client.ts` is
  defined but has zero callers anywhere outside its own colocated unit
  test (no hook, no page, no component); the other three's only would-be
  callers live in `resources/js/api/mock.ts`, which nothing imports. Not a
  test gap so much as a signal that these handlers, fixtures, and
  `mock.ts` are candidates for removal in a future cleanup (out of scope
  for this effort, since it's additive-only).
- `POST /logout` has a real caller (`Shell`), but no integration test
  renders the shell chrome that triggers it.
