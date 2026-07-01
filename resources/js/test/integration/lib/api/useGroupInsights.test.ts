import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { useGroupInsights } from '@/hooks/useGroupInsights'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'
import groupInsightsFixture from '../../../fixtures/insights/group.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('useGroupInsights', () => {
  it('resolves the real group insights shape', async () => {
    const { result } = renderHook(() => useGroupInsights({ tag: 7 }), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.data?.tag_id).toBe(groupInsightsFixture.data.tag_id)
    expect(result.current.data?.group_name).toBe(groupInsightsFixture.data.group_name)
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.get('/api/insights/group', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => useGroupInsights({ tag: 7 }), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBeTruthy()
  })
})
