import { renderHook, waitFor, act } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import {
  useCreatePlant,
  useUpdatePlant,
  useUploadPhoto,
  useSetCoverPhoto,
  useDeletePhoto,
} from '@/hooks/usePlantMutations'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'
import plantCreatedFixture from '../../../fixtures/plants/created-201.json'
import plantDetailFixture from '../../../fixtures/plants/detail-1.json'
import photoCreatedFixture from '../../../fixtures/photos/created-201.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('useCreatePlant', () => {
  it('creates a plant and resolves the real created shape', async () => {
    const requests: Array<{ body: unknown }> = []
    server.use(
      http.post('/api/plants', async ({ request }) => {
        requests.push({ body: await request.json() })
        return HttpResponse.json(plantCreatedFixture, { status: 201 })
      })
    )
    const { result } = renderHook(() => useCreatePlant(), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.mutateAsync({ payload: { common_name: 'Pothos' } })
    })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.data?.id).toBe(plantCreatedFixture.data.id)
    expect(result.current.data?.common_name).toBe(plantCreatedFixture.data.common_name)
    expect(requests[0]).toMatchObject({ body: { common_name: 'Pothos' } })
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.post('/api/plants', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => useCreatePlant(), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.mutateAsync({ payload: { common_name: 'Pothos' } }).catch(() => {})
    })
    await waitFor(() => expect(result.current.isError).toBe(true))
    expect(result.current.error).toBeTruthy()
  })
})

describe('useUpdatePlant', () => {
  it('updates a plant and resolves the real updated shape', async () => {
    const requests: Array<{ plantId: string; body: unknown }> = []
    server.use(
      http.patch('/api/plants/:id', async ({ request, params }) => {
        requests.push({ plantId: params.id as string, body: await request.json() })
        return HttpResponse.json(plantDetailFixture, { status: 200 })
      })
    )
    const { result } = renderHook(() => useUpdatePlant(1), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.mutateAsync({ common_name: 'Updated Pothos' })
    })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.data?.id).toBe(plantDetailFixture.data.id)
    expect(result.current.data?.common_name).toBe(plantDetailFixture.data.common_name)
    expect(requests[0]).toMatchObject({ plantId: '1', body: { common_name: 'Updated Pothos' } })
  })
})

describe('useUploadPhoto', () => {
  it('uploads a photo and resolves the real created shape', async () => {
    const requests: Array<{ plantId: string; photo: File | null }> = []
    server.use(
      http.post('/api/plants/:id/photos', async ({ request, params }) => {
        const form = await request.formData()
        requests.push({ plantId: params.id as string, photo: form.get('photo') as File | null })
        return HttpResponse.json(photoCreatedFixture, { status: 201 })
      })
    )
    const { result } = renderHook(() => useUploadPhoto(5), { wrapper: makeWrapper() })
    const file = new File(['x'], 'capture.png', { type: 'image/png' })
    await act(async () => {
      await result.current.mutateAsync({ file })
    })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.data?.id).toBe(photoCreatedFixture.data.id)
    expect(result.current.data?.path).toBe(photoCreatedFixture.data.path)
    // MSW's parsed File loses the original name in jsdom, so the content type
    // confirms the right field made it onto the request.
    expect(requests[0]?.plantId).toBe('5')
    expect(requests[0]?.photo?.type).toBe('image/png')
  })
})

describe('useSetCoverPhoto', () => {
  it('sets the cover photo and resolves the real updated plant shape', async () => {
    const requests: Array<{ plantId: string; body: unknown }> = []
    server.use(
      http.patch('/api/plants/:id', async ({ request, params }) => {
        requests.push({ plantId: params.id as string, body: await request.json() })
        return HttpResponse.json(plantDetailFixture, { status: 200 })
      })
    )
    const { result } = renderHook(() => useSetCoverPhoto(3), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.mutateAsync(4)
    })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.data?.id).toBe(plantDetailFixture.data.id)
    expect(result.current.data?.cover_photo_id).toBe(plantDetailFixture.data.cover_photo_id)
    expect(requests[0]).toMatchObject({ plantId: '3', body: { cover_photo_id: 4 } })
  })
})

describe('useDeletePhoto', () => {
  it('deletes a photo and resolves with no body', async () => {
    const requests: Array<{ photoId: string }> = []
    server.use(
      http.delete('/api/photos/:id', ({ params }) => {
        requests.push({ photoId: params.id as string })
        return new HttpResponse(null, { status: 204 })
      })
    )
    const { result } = renderHook(() => useDeletePhoto(3), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.mutateAsync(4)
    })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.data).toBeFalsy()
    expect(requests[0]).toMatchObject({ photoId: '4' })
  })
})
