import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import type { ReactNode } from 'react'
import { describe, expect, it, vi } from 'vitest'
import type { CareEvent } from '@/api/types'
import { LogWateringForm } from '@/components/forms/log-watering-form'
import { server } from '../../../handlers'
import { laravelValidationError } from '../../../handlers/_helpers'

function makeWrapper() {
  const qc = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={qc}>{children}</QueryClientProvider>
  }
}

// Field values mirror the captured resources/js/test/fixtures/care-events/watering.json.
const wateringEvent: CareEvent = {
  id: 42,
  plant_id: 5,
  care_event_type_id: 1,
  type: 'watering',
  occurred_at: '2026-06-28T18:45:00.000000Z',
  logged_by_user_id: 1,
  note: 'Soil was dry, gave a thorough watering.',
  created_at: '2026-06-28T19:45:26.000000Z',
  updated_at: '2026-06-28T19:45:26.000000Z',
  watering: { care_event_id: 42, amount_ml: null },
}

describe('LogWateringForm', () => {
  it('creates a watering through the real POST and calls onDone', async () => {
    const onDone = vi.fn()
    const requests: Array<{ plantId: string; body: unknown }> = []
    server.use(
      http.post('/api/plants/:id/waterings', async ({ request, params }) => {
        requests.push({ plantId: params.id as string, body: await request.json() })
        return HttpResponse.json({ data: { id: 51 } }, { status: 201 })
      })
    )
    render(<LogWateringForm plantId={5} onDone={onDone} />, { wrapper: makeWrapper() })

    await userEvent.type(screen.getByPlaceholderText('200'), '180')
    await userEvent.click(screen.getByRole('button', { name: /Log watering/ }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(requests[0]).toMatchObject({ plantId: '5', body: { amount_ml: 180, note: null } })
  })

  it('edits an existing watering through the real PATCH, prefilled from the event', async () => {
    const onDone = vi.fn()
    const requests: Array<{ eventId: string; body: unknown }> = []
    server.use(
      http.patch('/api/care-events/:id', async ({ request, params }) => {
        requests.push({ eventId: params.id as string, body: await request.json() })
        return HttpResponse.json({ data: { id: 42 } }, { status: 200 })
      })
    )
    render(<LogWateringForm plantId={5} onDone={onDone} event={wateringEvent} />, {
      wrapper: makeWrapper(),
    })

    await userEvent.click(screen.getByRole('button', { name: /Save changes/ }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(requests[0]).toMatchObject({
      eventId: '42',
      body: { amount_ml: null, note: 'Soil was dry, gave a thorough watering.' },
    })
  })

  it('maps a real 422 to the date field instead of a generic banner', async () => {
    const onDone = vi.fn()
    server.use(
      http.post('/api/plants/:id/waterings', () =>
        laravelValidationError({ occurred_at: ['The occurred at field is required.'] })
      )
    )
    render(<LogWateringForm plantId={5} onDone={onDone} />, { wrapper: makeWrapper() })

    await userEvent.click(screen.getByRole('button', { name: /Log watering/ }))

    expect(await screen.findByText('The occurred at field is required.')).toBeInTheDocument()
    expect(onDone).not.toHaveBeenCalled()
  })
})
