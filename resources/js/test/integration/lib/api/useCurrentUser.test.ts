import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { useCurrentUser } from '@/hooks/useCurrentUser'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'
import userFixture from '../../../fixtures/user.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('useCurrentUser', () => {
  it('resolves the real user shape', async () => {
    const { result } = renderHook(() => useCurrentUser(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.user?.id).toBe(userFixture.id)
    expect(result.current.user?.email).toBe(userFixture.email)
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.get('/api/user', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => useCurrentUser(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBeTruthy()
  })
})
