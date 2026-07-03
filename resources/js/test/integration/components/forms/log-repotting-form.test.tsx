import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import type { ReactNode } from 'react'
import { describe, expect, it, vi } from 'vitest'
import type { CareEvent } from '@/api/types'
import { LogRepottingForm } from '@/components/forms/log-repotting-form'
import { server } from '../../../handlers'

function makeWrapper() {
  const qc = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={qc}>{children}</QueryClientProvider>
  }
}

// Field values mirror the captured resources/js/test/fixtures/care-events/repotting.json.
const repottingEvent: CareEvent = {
  id: 49,
  plant_id: 5,
  care_event_type_id: 3,
  type: 'repotting',
  occurred_at: '2026-06-30T12:00:00.000000Z',
  logged_by_user_id: 2,
  note: null,
  created_at: '2026-07-01T00:18:40.000000Z',
  updated_at: '2026-07-01T00:18:40.000000Z',
  repotting: {
    care_event_id: 49,
    soil_recipe: 'Standard tropical mix',
    pot_size_value: 6,
    pot_size_unit: 'in',
    fertilizer_added: false,
  },
}

describe('LogRepottingForm', () => {
  it('creates a repotting through the real POST and calls onDone when no fertilizer was added', async () => {
    const onDone = vi.fn()
    const onLogFertilizer = vi.fn()
    const requests: unknown[] = []
    server.use(
      http.post('/api/plants/:id/care-events', async ({ request }) => {
        requests.push(await request.json())
        return HttpResponse.json({ data: { id: 70 } }, { status: 201 })
      })
    )
    render(<LogRepottingForm plantId={5} onDone={onDone} onLogFertilizer={onLogFertilizer} />, {
      wrapper: makeWrapper(),
    })

    await userEvent.click(screen.getByRole('button', { name: /Log repotting/ }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(requests[0]).toMatchObject({
      type: 'repotting',
      soil_recipe: null,
      pot_size_value: null,
      pot_size_unit: 'in',
      fertilizer_added: false,
      note: null,
    })
    expect(onLogFertilizer).not.toHaveBeenCalled()
  })

  it('chains onLogFertilizer with the same timestamp sent in the real request', async () => {
    const onDone = vi.fn()
    const onLogFertilizer = vi.fn()
    const requests: Array<{ occurred_at: string }> = []
    server.use(
      http.post('/api/plants/:id/care-events', async ({ request }) => {
        const body = (await request.json()) as { occurred_at: string }
        requests.push(body)
        return HttpResponse.json({ data: { id: 71 } }, { status: 201 })
      })
    )
    render(<LogRepottingForm plantId={5} onDone={onDone} onLogFertilizer={onLogFertilizer} />, {
      wrapper: makeWrapper(),
    })

    await userEvent.click(screen.getByRole('switch'))
    await userEvent.click(screen.getByRole('button', { name: /Log repotting/ }))

    await waitFor(() => expect(onLogFertilizer).toHaveBeenCalled())
    expect(onLogFertilizer).toHaveBeenCalledWith(requests[0]?.occurred_at)
    expect(onDone).not.toHaveBeenCalled()
  })

  it('edits an existing repotting through the real PATCH and does not chain fertilizing', async () => {
    const onDone = vi.fn()
    const onLogFertilizer = vi.fn()
    const requests: unknown[] = []
    server.use(
      http.patch('/api/care-events/:id', async ({ request }) => {
        requests.push(await request.json())
        return HttpResponse.json({ data: { id: 49 } }, { status: 200 })
      })
    )
    render(
      <LogRepottingForm
        plantId={5}
        onDone={onDone}
        onLogFertilizer={onLogFertilizer}
        event={repottingEvent}
      />,
      { wrapper: makeWrapper() }
    )

    expect(screen.getByDisplayValue('Standard tropical mix')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Save changes/ }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(requests[0]).toMatchObject({
      soil_recipe: 'Standard tropical mix',
      pot_size_value: 6,
      pot_size_unit: 'in',
      fertilizer_added: false,
    })
    expect(onLogFertilizer).not.toHaveBeenCalled()
  })
})
