import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { useDashboard } from '@/hooks/useDashboard'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'
import dashboardFixture from '../../../fixtures/dashboard/populated.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('useDashboard', () => {
  it('resolves the real dashboard shape', async () => {
    const { result } = renderHook(() => useDashboard(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.data).toHaveProperty('due_for_care')
    expect(result.current.data).toHaveProperty('recent_activity')
    expect(result.current.data).toHaveProperty('flagged_problems')
    expect(result.current.data?.recent_activity).toHaveLength(
      dashboardFixture.data.recent_activity.length
    )
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.get('/api/dashboard', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => useDashboard(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBeTruthy()
  })
})
