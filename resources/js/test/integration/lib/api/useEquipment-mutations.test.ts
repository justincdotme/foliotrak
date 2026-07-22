import { renderHook, waitFor, act } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { useCreateEquipment, useUpdateEquipment, useDeleteEquipment } from '@/hooks/useEquipment'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'
import equipmentCreatedFixture from '../../../fixtures/equipment/created-201.json'
import equipmentUpdatedFixture from '../../../fixtures/equipment/updated.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('useCreateEquipment', () => {
  it('creates equipment and resolves the real created shape', async () => {
    const requests: Array<{ body: unknown }> = []
    server.use(
      http.post('/api/equipment', async ({ request }) => {
        requests.push({ body: await request.json() })
        return HttpResponse.json(equipmentCreatedFixture, { status: 201 })
      })
    )
    const { result } = renderHook(() => useCreateEquipment(), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.mutateAsync('Moisture Meter')
    })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.data).toEqual(equipmentCreatedFixture.data)
    expect(requests[0]).toMatchObject({ body: { label: 'Moisture Meter' } })
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.post('/api/equipment', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => useCreateEquipment(), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.mutateAsync('Moisture Meter').catch(() => {})
    })
    await waitFor(() => expect(result.current.isError).toBe(true))
    expect(result.current.error).toBeTruthy()
  })
})

describe('useUpdateEquipment', () => {
  it('updates equipment and resolves the real updated shape', async () => {
    const requests: Array<{ equipmentId: string; body: unknown }> = []
    server.use(
      http.patch('/api/equipment/:id', async ({ request, params }) => {
        requests.push({ equipmentId: params.id as string, body: await request.json() })
        return HttpResponse.json(equipmentUpdatedFixture, { status: 200 })
      })
    )
    const { result } = renderHook(() => useUpdateEquipment(), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.mutateAsync({ id: 10, payload: { label: 'Moisture Meter (updated)' } })
    })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.data).toEqual(equipmentUpdatedFixture.data)
    expect(requests[0]).toMatchObject({
      equipmentId: '10',
      body: { label: 'Moisture Meter (updated)' },
    })
  })
})

describe('useDeleteEquipment', () => {
  it('deletes equipment and resolves with no body', async () => {
    const requests: Array<{ equipmentId: string }> = []
    server.use(
      http.delete('/api/equipment/:id', ({ params }) => {
        requests.push({ equipmentId: params.id as string })
        return new HttpResponse(null, { status: 204 })
      })
    )
    const { result } = renderHook(() => useDeleteEquipment(), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.mutateAsync(10)
    })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.data).toBeFalsy()
    expect(requests[0]).toMatchObject({ equipmentId: '10' })
  })
})
