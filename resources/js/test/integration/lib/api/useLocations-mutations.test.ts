import { renderHook, waitFor, act } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { useCreateLocation } from '@/hooks/useLocations'
import { server } from '../../../handlers'
import locationCreatedFixture from '../../../fixtures/locations/created-201.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('useCreateLocation', () => {
  it('creates a location and resolves the real created shape', async () => {
    const requests: Array<{ body: unknown }> = []
    server.use(
      http.post('/api/locations', async ({ request }) => {
        requests.push({ body: await request.json() })
        return HttpResponse.json(locationCreatedFixture, { status: 201 })
      })
    )
    const { result } = renderHook(() => useCreateLocation(), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.mutateAsync('Greenhouse')
    })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.data).toEqual(locationCreatedFixture.data)
    expect(requests[0]).toMatchObject({ body: { name: 'Greenhouse' } })
  })
})
