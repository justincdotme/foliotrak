import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { useSettings } from '@/hooks/useSettings'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'
import settingsFixture from '../../../fixtures/settings.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('useSettings', () => {
  it('resolves the real settings shape', async () => {
    const { result } = renderHook(() => useSettings(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.data?.temperature_unit).toBe(settingsFixture.data.temperature_unit)
    expect(result.current.data?.pushover_user_key).toBe(settingsFixture.data.pushover_user_key)
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.get('/api/settings', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => useSettings(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBeTruthy()
  })
})
