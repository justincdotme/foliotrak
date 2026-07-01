import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import type { ReactNode } from 'react'
import { describe, expect, it, vi } from 'vitest'
import type { CareEvent } from '@/api/types'
import { LogFertilizingForm } from '@/components/forms/log-fertilizing-form'
import { server } from '../../../handlers'

function makeWrapper() {
  const qc = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={qc}>{children}</QueryClientProvider>
  }
}

// Field values mirror the captured resources/js/test/fixtures/care-events/fertilizing.json.
const liquidEvent: CareEvent = {
  id: 48,
  plant_id: 5,
  care_event_type_id: 2,
  type: 'fertilizing',
  occurred_at: '2026-06-30T12:00:00.000000Z',
  logged_by_user_id: 2,
  note: null,
  created_at: '2026-07-01T00:18:30.000000Z',
  updated_at: '2026-07-01T00:18:30.000000Z',
  fertilizing: {
    care_event_id: 48,
    fertilizer_form_id: 1,
    form: 'liquid',
    brand: null,
    product: null,
    npk_n: null,
    npk_p: null,
    npk_k: null,
    dose_pct: null,
    amount_ml: 50,
    nutrients: [],
  },
}

describe('LogFertilizingForm', () => {
  it('auto-defaults to the real liquid form id from the lookup and submits with no nutrients', async () => {
    const onDone = vi.fn()
    const requests: unknown[] = []
    server.use(
      http.post('/api/plants/:id/fertilizings', async ({ request }) => {
        requests.push(await request.json())
        return HttpResponse.json({ data: { id: 60 } }, { status: 201 })
      })
    )
    render(<LogFertilizingForm plantId={5} onDone={onDone} />, { wrapper: makeWrapper() })

    // The fertilizer-form lookup (/api/fertilizer-forms) resolves the auto-default effect.
    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Form' })).toHaveValue('1'), {
      timeout: 2000,
    })
    await userEvent.click(screen.getByRole('button', { name: /Log fertilizing/ }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(requests[0]).toMatchObject({ fertilizer_form_id: 1, nutrients: [], dose_pct: 50 })
  })

  it('includes a real nutrient from the lookup when the organic form is picked', async () => {
    const onDone = vi.fn()
    const requests: unknown[] = []
    server.use(
      http.post('/api/plants/:id/fertilizings', async ({ request }) => {
        requests.push(await request.json())
        return HttpResponse.json({ data: { id: 61 } }, { status: 201 })
      })
    )
    render(<LogFertilizingForm plantId={5} onDone={onDone} />, { wrapper: makeWrapper() })

    await screen.findByRole('option', { name: 'Organic' }, { timeout: 2000 })
    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Form' }), '4')
    await userEvent.click(screen.getByRole('button', { name: /^Add$/ }))
    await userEvent.click(screen.getByRole('button', { name: /Log fertilizing/ }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(requests[0]).toMatchObject({
      fertilizer_form_id: 4,
      nutrients: [{ nutrient_id: 1, note: null }],
    })
  })

  it('edits an existing liquid event through the real PATCH', async () => {
    const onDone = vi.fn()
    const createSpy = vi.fn()
    const patchRequests: unknown[] = []
    server.use(
      http.post('/api/plants/:id/fertilizings', () => {
        createSpy()
        return HttpResponse.json({ data: { id: 62 } }, { status: 201 })
      }),
      http.patch('/api/care-events/:id', async ({ request }) => {
        patchRequests.push(await request.json())
        return HttpResponse.json({ data: { id: 48 } }, { status: 200 })
      })
    )
    render(<LogFertilizingForm plantId={5} onDone={onDone} event={liquidEvent} />, {
      wrapper: makeWrapper(),
    })

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Form' })).toHaveValue('1'), {
      timeout: 2000,
    })

    await userEvent.click(screen.getByRole('button', { name: /Save changes/ }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(patchRequests[0]).toMatchObject({
      fertilizer_form_id: 1,
      amount_ml: 50,
      nutrients: [],
    })
    expect(createSpy).not.toHaveBeenCalled()
  })
})
