import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { useTimeline } from '@/hooks/useTimeline'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'
import plantTimelineFixture from '../../../fixtures/plant/timeline-1.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('useTimeline', () => {
  it('resolves the real timeline shape', async () => {
    const { result } = renderHook(() => useTimeline(3), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.data?.events).toHaveLength(plantTimelineFixture.data.events.length)
    expect(result.current.data?.events?.[0]?.type).toBe(plantTimelineFixture.data.events[0]?.type)
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.get('/api/plants/:id/timeline', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => useTimeline(3), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBeTruthy()
  })
})
