import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import type { ReactNode } from 'react'
import { describe, expect, it, vi } from 'vitest'
import type { CareEvent } from '@/api/types'
import { RelocationEditForm } from '@/components/forms/relocation-edit-form'
import { server } from '../../../handlers'

function makeWrapper() {
  const qc = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={qc}>{children}</QueryClientProvider>
  }
}

// Field values mirror the captured resources/js/test/fixtures/care-events/relocation.json.
// The lookup at fixtures/lookups/locations.json includes both endpoints: id 1 "step shelf"
// (the origin) and id 2 "Guest Bedroom" (the current destination), plus id 3 "Wet Bar".
const relocationEvent: CareEvent = {
  id: 41,
  plant_id: 3,
  care_event_type_id: 5,
  type: 'relocation',
  occurred_at: '2026-06-27T22:01:26.000000Z',
  logged_by_user_id: 1,
  note: null,
  created_at: '2026-06-27T22:01:26.000000Z',
  updated_at: '2026-06-27T22:01:26.000000Z',
  relocation: {
    care_event_id: 41,
    from_location: { id: 1, name: 'step shelf' },
    to_location: { id: 2, name: 'Guest Bedroom' },
  },
}

describe('RelocationEditForm', () => {
  it('shows the origin read-only and submits the prefilled destination through the real PATCH', async () => {
    const onDone = vi.fn()
    const requests: Array<{ eventId: string; body: unknown }> = []
    server.use(
      http.patch('/api/care-events/:id', async ({ request, params }) => {
        requests.push({ eventId: params.id as string, body: await request.json() })
        return HttpResponse.json({ data: { id: 41 } }, { status: 200 })
      })
    )
    render(<RelocationEditForm plantId={3} event={relocationEvent} onDone={onDone} />, {
      wrapper: makeWrapper(),
    })

    expect(screen.getByText('step shelf')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Save changes/ }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(requests[0]).toMatchObject({
      eventId: '41',
      body: { to_location_id: 2, note: null },
    })
  })

  it('changes the destination through the real location lookup and submits the new id', async () => {
    const onDone = vi.fn()
    const requests: unknown[] = []
    server.use(
      http.patch('/api/care-events/:id', async ({ request }) => {
        requests.push(await request.json())
        return HttpResponse.json({ data: { id: 41 } }, { status: 200 })
      })
    )
    render(<RelocationEditForm plantId={3} event={relocationEvent} onDone={onDone} />, {
      wrapper: makeWrapper(),
    })

    const combobox = screen.getByRole('combobox')
    // Wait for /api/locations to resolve so the prefilled id 2 renders as its real name.
    await waitFor(() => expect(combobox).toHaveValue('Guest Bedroom'), { timeout: 2000 })

    await userEvent.click(combobox)
    await userEvent.clear(combobox)
    await userEvent.type(combobox, 'Wet Bar')
    await userEvent.click(screen.getByRole('option', { name: 'Wet Bar' }))

    await userEvent.click(screen.getByRole('button', { name: /Save changes/ }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(requests[0]).toMatchObject({ to_location_id: 3 })
  })
})
