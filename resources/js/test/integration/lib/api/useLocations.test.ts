import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { useLocations } from '@/hooks/useLocations'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'
import locationsFixture from '../../../fixtures/lookups/locations.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('useLocations', () => {
  it('resolves the real locations list shape', async () => {
    const { result } = renderHook(() => useLocations(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.data).toHaveLength(locationsFixture.data.length)
    expect(result.current.data?.[0]?.name).toBe(locationsFixture.data[0]?.name)
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.get('/api/locations', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => useLocations(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBeTruthy()
  })
})
