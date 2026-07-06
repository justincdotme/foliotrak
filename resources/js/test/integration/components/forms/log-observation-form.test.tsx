import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import type { ReactNode } from 'react'
import { describe, expect, it, vi } from 'vitest'
import type { CareEvent } from '@/api/types'
import { LogObservationForm } from '@/components/forms/log-observation-form'
import { TooltipProvider } from '@/components/ui/tooltip'
import { server } from '../../../handlers'
import photoCreated from '../../../fixtures/photos/created-201.json'

function makeWrapper() {
  const qc = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <TooltipProvider>
        <QueryClientProvider client={qc}>{children}</QueryClientProvider>
      </TooltipProvider>
    )
  }
}

// Field values mirror the captured resources/js/test/fixtures/care-events/observation.json,
// extended with symptoms to exercise the seeded-vs-custom split.
const observationEvent: CareEvent = {
  id: 39,
  plant_id: 3,
  care_event_type_id: 4,
  type: 'observation',
  occurred_at: '2026-06-26T15:00:00.000000Z',
  logged_by_user_id: 1,
  note: null,
  created_at: '2026-06-27T05:02:08.000000Z',
  updated_at: '2026-06-27T05:02:08.000000Z',
  observation: {
    care_event_id: 39,
    overall_health: 1,
    health_note: 'Showing some stress on lower leaves.',
    light_level: 5,
    growth_rate: 'slow',
    growth_note: null,
    leaf_size_mm: null,
    weight_grams: null,
    weight: null,
    ambient_humidity_pct: null,
    ambient_temp_c: null,
    ambient_temp_display: null,
    temperature_unit: 'F',
    soil_moisture_relative: null,
    soil_moisture_precise: null,
    symptoms: [
      {
        id: 1,
        category: 'leaf',
        key: 'yellow_leaf',
        label: 'Yellowing leaves',
        sort_order: 1,
        is_custom: false,
      },
      {
        id: 99,
        category: 'custom',
        key: 'curled_tips',
        label: 'curled tips',
        sort_order: 99,
        is_custom: true,
      },
    ],
  },
}

describe('LogObservationForm', () => {
  // Six real user interactions plus two real network round trips make this the
  // heaviest test in the suite; V8 coverage instrumentation pushes it past the
  // default 5s testTimeout even though it completes in ~2s uninstrumented.
  it('sends a real symptom, a custom symptom, and weight, then links the uploaded photo to the real created event', async () => {
    const onDone = vi.fn()
    const requests: unknown[] = []
    const photoRequests: FormData[] = []
    server.use(
      http.post('/api/plants/:id/care-events', async ({ request }) => {
        requests.push(await request.json())
        // The real handler envelopes fixtures/care-events/observation.json (id 39).
        return HttpResponse.json({ data: { id: 39 } }, { status: 201 })
      }),
      http.post('/api/plants/:id/photos', async ({ request }) => {
        photoRequests.push(await request.formData())
        return HttpResponse.json(photoCreated, { status: 201 })
      })
    )
    const { container } = render(<LogObservationForm plantId={3} onDone={onDone} />, {
      wrapper: makeWrapper(),
    })

    const symptomChip = await screen.findByRole(
      'button',
      { name: /Yellowing leaves/ },
      { timeout: 2000 }
    )
    await userEvent.click(symptomChip)
    await userEvent.type(screen.getByLabelText('Custom symptom'), 'curled tips')
    await userEvent.click(screen.getByRole('button', { name: /^Add$/ }))

    await userEvent.clear(screen.getByLabelText('Grams'))
    await userEvent.type(screen.getByLabelText('Grams'), '120')

    const file = new File(['x'], 'leaf.jpg', { type: 'image/jpeg' })
    await userEvent.upload(container.querySelector('input[type="file"]') as HTMLInputElement, file)

    await userEvent.click(screen.getByRole('button', { name: /Log observation/ }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(requests[0]).toMatchObject({
      type: 'observation',
      weight: { lb: 0, oz: 0, g: 120 },
      symptom_ids: [1],
      custom_symptoms: ['curled tips'],
    })
    expect(photoRequests[0]?.get('care_event_id')).toBe('39')
    // The uploaded blob crosses a real multipart request; MSW's parsed File loses the
    // original name in jsdom, so the content type is what confirms the right field made it.
    const uploadedFile = photoRequests[0]?.get('photo') as File
    expect(uploadedFile?.type).toBe('image/jpeg')
  }, 10000)

  it('omits weight in the real request body when no components are entered', async () => {
    const onDone = vi.fn()
    const requests: unknown[] = []
    server.use(
      http.post('/api/plants/:id/care-events', async ({ request }) => {
        requests.push(await request.json())
        return HttpResponse.json({ data: { id: 40 } }, { status: 201 })
      })
    )
    render(<LogObservationForm plantId={3} onDone={onDone} />, { wrapper: makeWrapper() })

    await userEvent.click(screen.getByRole('button', { name: /Log observation/ }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(requests[0]).toMatchObject({ type: 'observation', weight: null })
  })

  it('prefills from a real observation event and routes the edit to the real PATCH', async () => {
    const onDone = vi.fn()
    const createSpy = vi.fn()
    const patchRequests: unknown[] = []
    server.use(
      http.post('/api/plants/:id/care-events', () => {
        createSpy()
        return HttpResponse.json({ data: { id: 41 } }, { status: 201 })
      }),
      http.patch('/api/care-events/:id', async ({ request }) => {
        patchRequests.push(await request.json())
        return HttpResponse.json({ data: { id: 39 } }, { status: 200 })
      })
    )
    render(<LogObservationForm plantId={3} onDone={onDone} event={observationEvent} />, {
      wrapper: makeWrapper(),
    })

    // The custom symptom is restored as an editable chip, not a lookup chip.
    expect(await screen.findByText('curled tips')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Save changes/ }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(patchRequests[0]).toMatchObject({
      symptom_ids: [1],
      custom_symptoms: ['curled tips'],
    })
    expect(createSpy).not.toHaveBeenCalled()
  })
})
