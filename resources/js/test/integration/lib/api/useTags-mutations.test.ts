import { renderHook, waitFor, act } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { useCreateTag, useUpdateTag, useDeleteTag } from '@/hooks/useTags'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'
import tagCreatedFixture from '../../../fixtures/tags/created-201.json'
import tagUpdatedFixture from '../../../fixtures/tags/updated.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('useCreateTag', () => {
  it('creates a tag and resolves the real created shape', async () => {
    const requests: Array<{ body: unknown }> = []
    server.use(
      http.post('/api/tags', async ({ request }) => {
        requests.push({ body: await request.json() })
        return HttpResponse.json(tagCreatedFixture, { status: 201 })
      })
    )
    const { result } = renderHook(() => useCreateTag(), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.mutateAsync('Succulents')
    })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.data).toEqual(tagCreatedFixture.data)
    expect(requests[0]).toMatchObject({ body: { name: 'Succulents' } })
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.post('/api/tags', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => useCreateTag(), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.mutateAsync('Succulents').catch(() => {})
    })
    await waitFor(() => expect(result.current.isError).toBe(true))
    expect(result.current.error).toBeTruthy()
  })
})

describe('useUpdateTag', () => {
  it('updates a tag and resolves the real updated shape', async () => {
    const requests: Array<{ tagId: string; body: unknown }> = []
    server.use(
      http.patch('/api/tags/:id', async ({ request, params }) => {
        requests.push({ tagId: params.id as string, body: await request.json() })
        return HttpResponse.json(tagUpdatedFixture, { status: 200 })
      })
    )
    const { result } = renderHook(() => useUpdateTag(), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.mutateAsync({ id: 12, payload: { name: 'Succulents (updated)' } })
    })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.data).toEqual(tagUpdatedFixture.data)
    expect(requests[0]).toMatchObject({
      tagId: '12',
      body: { name: 'Succulents (updated)' },
    })
  })
})

describe('useDeleteTag', () => {
  it('deletes a tag and resolves with no body', async () => {
    const requests: Array<{ tagId: string }> = []
    server.use(
      http.delete('/api/tags/:id', ({ params }) => {
        requests.push({ tagId: params.id as string })
        return new HttpResponse(null, { status: 204 })
      })
    )
    const { result } = renderHook(() => useDeleteTag(), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.mutateAsync(12)
    })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.data).toBeFalsy()
    expect(requests[0]).toMatchObject({ tagId: '12' })
  })
})

describe('useCreateTag - invalidation', () => {
  it('invalidates plants and insights group data after creating a tag', async () => {
    const qc = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
    const wrapper = ({ children }: { children: React.ReactNode }) =>
      React.createElement(QueryClientProvider, { client: qc }, children)
    qc.setQueryData(['plants'], [])
    qc.setQueryData(['insights', 'group', { tag: 1 }], { comparison: [] })

    server.use(http.post('/api/tags', () => HttpResponse.json(tagCreatedFixture, { status: 201 })))
    const { result } = renderHook(() => useCreateTag(), { wrapper })
    await act(async () => {
      await result.current.mutateAsync('Succulents')
    })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))

    expect(qc.getQueryState(['plants'])?.isInvalidated).toBe(true)
    expect(qc.getQueryState(['insights', 'group', { tag: 1 }])?.isInvalidated).toBe(true)
  })
})

describe('useUpdateTag - invalidation', () => {
  it('invalidates plants and insights group data after renaming a tag', async () => {
    const qc = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
    const wrapper = ({ children }: { children: React.ReactNode }) =>
      React.createElement(QueryClientProvider, { client: qc }, children)
    qc.setQueryData(['plants'], [])
    qc.setQueryData(['insights', 'group', { tag: 1 }], { comparison: [] })

    server.use(
      http.patch('/api/tags/:id', () => HttpResponse.json(tagUpdatedFixture, { status: 200 }))
    )
    const { result } = renderHook(() => useUpdateTag(), { wrapper })
    await act(async () => {
      await result.current.mutateAsync({ id: 12, payload: { name: 'Succulents (updated)' } })
    })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))

    expect(qc.getQueryState(['plants'])?.isInvalidated).toBe(true)
    expect(qc.getQueryState(['insights', 'group', { tag: 1 }])?.isInvalidated).toBe(true)
  })
})

describe('useDeleteTag - invalidation', () => {
  it('invalidates plants and insights group data after deleting a tag', async () => {
    const qc = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
    const wrapper = ({ children }: { children: React.ReactNode }) =>
      React.createElement(QueryClientProvider, { client: qc }, children)
    qc.setQueryData(['plants'], [])
    qc.setQueryData(['insights', 'group', { tag: 1 }], { comparison: [] })

    server.use(http.delete('/api/tags/:id', () => new HttpResponse(null, { status: 204 })))
    const { result } = renderHook(() => useDeleteTag(), { wrapper })
    await act(async () => {
      await result.current.mutateAsync(12)
    })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))

    expect(qc.getQueryState(['plants'])?.isInvalidated).toBe(true)
    expect(qc.getQueryState(['insights', 'group', { tag: 1 }])?.isInvalidated).toBe(true)
  })
})
