import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { useTags } from '@/hooks/useTags'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'
import tagsFixture from '../../../fixtures/lookups/tags.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('useTags', () => {
  it('resolves the real tags list shape', async () => {
    const { result } = renderHook(() => useTags(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.data).toHaveLength(tagsFixture.data.length)
    expect(result.current.data?.[0]?.name).toBe(tagsFixture.data[0]?.name)
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.get('/api/tags', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => useTags(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBeTruthy()
  })
})
