import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import type { ReactNode } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { AddPlantForm } from '@/components/forms/add-plant-form'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'

function makeWrapper() {
  const qc = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={qc}>{children}</QueryClientProvider>
  }
}

describe('AddPlantForm', () => {
  it('sends the typed name in the real request body and calls onDone on success', async () => {
    const onDone = vi.fn()
    const requestBodies: unknown[] = []
    server.use(
      http.post('/api/plants', async ({ request }) => {
        requestBodies.push(await request.json())
        return HttpResponse.json({ data: { id: 99, common_name: 'Pothos' } }, { status: 201 })
      })
    )
    render(<AddPlantForm onDone={onDone} />, { wrapper: makeWrapper() })

    await userEvent.type(screen.getByPlaceholderText(/Pothos, Monstera/), 'Pothos')
    await userEvent.click(screen.getByRole('button', { name: /add plant/i }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(requestBodies[0]).toMatchObject({
      common_name: 'Pothos',
      scientific_name: null,
      nickname: null,
      gbif_key: null,
      location_id: null,
      acquired_on: null,
      tag_ids: [],
    })
  })

  it('shows the real server error and does not call onDone when creation fails', async () => {
    const onDone = vi.fn()
    server.use(http.post('/api/plants', () => jsonMessage(500, 'Could not save plant.')))
    render(<AddPlantForm onDone={onDone} />, { wrapper: makeWrapper() })

    await userEvent.type(screen.getByPlaceholderText(/Pothos, Monstera/), 'Pothos')
    await userEvent.click(screen.getByRole('button', { name: /add plant/i }))

    expect(await screen.findByText('Could not save plant.')).toBeInTheDocument()
    expect(onDone).not.toHaveBeenCalled()
  })
})
