import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { usePlant } from '@/hooks/usePlant'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'
import plantDetailFixture from '../../../fixtures/plants/detail-1.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('usePlant', () => {
  it('resolves the real plant detail shape', async () => {
    const { result } = renderHook(() => usePlant(3), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.data?.id).toBe(plantDetailFixture.data.id)
    expect(result.current.data?.common_name).toBe(plantDetailFixture.data.common_name)
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.get('/api/plants/:id', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => usePlant(3), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBeTruthy()
  })
})
