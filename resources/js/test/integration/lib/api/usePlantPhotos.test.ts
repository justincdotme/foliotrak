import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { usePlantPhotos } from '@/hooks/usePlantPhotos'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'
import plantPhotosFixture from '../../../fixtures/plant/photos-1.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('usePlantPhotos', () => {
  it('resolves the real plant photos shape', async () => {
    const { result } = renderHook(() => usePlantPhotos(3), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.data).toHaveLength(plantPhotosFixture.data.length)
    expect(result.current.data?.[0]?.original_filename).toBe(
      plantPhotosFixture.data[0]?.original_filename
    )
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.get('/api/plants/:id/photos', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => usePlantPhotos(3), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBeTruthy()
  })
})
