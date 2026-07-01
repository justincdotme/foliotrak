import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { usePlants } from '@/hooks/usePlants'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'
import plantsListFixture from '../../../fixtures/plants/list.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('usePlants', () => {
  it('resolves the real plants list shape', async () => {
    const { result } = renderHook(() => usePlants(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.data).toHaveLength(plantsListFixture.data.length)
    expect(result.current.data?.[0]?.common_name).toBe(plantsListFixture.data[0]?.common_name)
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.get('/api/plants', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => usePlants(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBeTruthy()
  })
})
