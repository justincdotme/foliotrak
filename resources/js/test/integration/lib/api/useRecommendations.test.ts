import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { useRecommendations } from '@/hooks/useRecommendations'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'
import recommendationsFixture from '../../../fixtures/plant/recommendations-1.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('useRecommendations', () => {
  it('resolves the real recommendations shape', async () => {
    const { result } = renderHook(() => useRecommendations(3), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.data?.plant_id).toBe(recommendationsFixture.data.plant_id)
    expect(result.current.data?.gate?.state).toBe(recommendationsFixture.data.gate.state)
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.get('/api/plants/:id/recommendations', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => useRecommendations(3), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBeTruthy()
  })
})
