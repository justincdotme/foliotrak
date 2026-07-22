import { renderHook, waitFor, act } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { useUpdateSettings } from '@/hooks/useSettings'
import { server } from '../../../handlers'
import settingsUpdatedFixture from '../../../fixtures/settings/updated.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('useUpdateSettings', () => {
  it('updates settings and resolves the real updated shape', async () => {
    const requests: Array<{ body: unknown }> = []
    server.use(
      http.patch('/api/settings', async ({ request }) => {
        requests.push({ body: await request.json() })
        return HttpResponse.json(settingsUpdatedFixture, { status: 200 })
      })
    )
    const { result } = renderHook(() => useUpdateSettings(), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.mutateAsync({ pushover_user_key: 'abc123' })
    })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(requests[0]).toMatchObject({ body: { pushover_user_key: 'abc123' } })
    expect(result.current.data).toEqual(settingsUpdatedFixture.data)
  })
})
