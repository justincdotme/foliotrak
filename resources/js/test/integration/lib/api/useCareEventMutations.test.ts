import { renderHook, waitFor, act } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { useCareEventMutations } from '@/hooks/useCareEventMutations'
import { server } from '../../../handlers'
import wateringFixture from '../../../fixtures/care-events/watering.json'
import fertilizingFixture from '../../../fixtures/care-events/fertilizing.json'
import repottingFixture from '../../../fixtures/care-events/repotting.json'
import observationFixture from '../../../fixtures/care-events/observation.json'
import careEventUpdatedFixture from '../../../fixtures/care-events/updated.json'
import photoCreatedFixture from '../../../fixtures/photos/created-201.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('useCareEventMutations - createWatering', () => {
  it('creates a watering event and resolves the bare fixture the handler envelopes', async () => {
    const requests: Array<{ plantId: string; body: unknown }> = []
    server.use(
      http.post('/api/plants/:id/care-events', async ({ request, params }) => {
        requests.push({ plantId: params.id as string, body: await request.json() })
        return HttpResponse.json({ data: wateringFixture }, { status: 201 })
      })
    )
    const { result } = renderHook(() => useCareEventMutations(5), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.createWatering.mutateAsync({ occurred_at: '2026-06-28T18:45:00Z' })
    })
    await waitFor(() => expect(result.current.createWatering.isSuccess).toBe(true))
    expect(result.current.createWatering.data).toEqual(wateringFixture)
    expect(requests[0]).toMatchObject({
      plantId: '5',
      body: { type: 'watering', occurred_at: '2026-06-28T18:45:00Z' },
    })
  })
})

describe('useCareEventMutations - createFertilizing', () => {
  it('creates a fertilizing event and resolves the enveloped fixture data', async () => {
    const requests: Array<{ plantId: string; body: unknown }> = []
    server.use(
      http.post('/api/plants/:id/care-events', async ({ request, params }) => {
        requests.push({ plantId: params.id as string, body: await request.json() })
        return HttpResponse.json({ data: fertilizingFixture }, { status: 201 })
      })
    )
    const { result } = renderHook(() => useCareEventMutations(5), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.createFertilizing.mutateAsync({
        occurred_at: '2026-06-30T12:00:00Z',
        fertilizer_form_id: 1,
      })
    })
    await waitFor(() => expect(result.current.createFertilizing.isSuccess).toBe(true))
    expect(result.current.createFertilizing.data).toEqual(fertilizingFixture)
    expect(requests[0]).toMatchObject({
      plantId: '5',
      body: { type: 'fertilizing', occurred_at: '2026-06-30T12:00:00Z', fertilizer_form_id: 1 },
    })
  })
})

describe('useCareEventMutations - createRepotting', () => {
  it('creates a repotting event and resolves the enveloped fixture data', async () => {
    const requests: Array<{ plantId: string; body: unknown }> = []
    server.use(
      http.post('/api/plants/:id/care-events', async ({ request, params }) => {
        requests.push({ plantId: params.id as string, body: await request.json() })
        return HttpResponse.json({ data: repottingFixture }, { status: 201 })
      })
    )
    const { result } = renderHook(() => useCareEventMutations(5), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.createRepotting.mutateAsync({ occurred_at: '2026-06-30T12:00:00Z' })
    })
    await waitFor(() => expect(result.current.createRepotting.isSuccess).toBe(true))
    expect(result.current.createRepotting.data).toEqual(repottingFixture)
    expect(requests[0]).toMatchObject({
      plantId: '5',
      body: { type: 'repotting', occurred_at: '2026-06-30T12:00:00Z' },
    })
  })
})

describe('useCareEventMutations - createObservation', () => {
  it('creates an observation event and resolves the bare fixture the handler envelopes', async () => {
    const requests: Array<{ plantId: string; body: unknown }> = []
    server.use(
      http.post('/api/plants/:id/care-events', async ({ request, params }) => {
        requests.push({ plantId: params.id as string, body: await request.json() })
        return HttpResponse.json({ data: observationFixture }, { status: 201 })
      })
    )
    const { result } = renderHook(() => useCareEventMutations(3), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.createObservation.mutateAsync({ occurred_at: '2026-06-26T15:00:00Z' })
    })
    await waitFor(() => expect(result.current.createObservation.isSuccess).toBe(true))
    expect(result.current.createObservation.data).toEqual(observationFixture)
    expect(requests[0]).toMatchObject({
      plantId: '3',
      body: { type: 'observation', occurred_at: '2026-06-26T15:00:00Z' },
    })
  })
})

describe('useCareEventMutations - updateEvent', () => {
  it('updates a care event and resolves the enveloped fixture data', async () => {
    const requests: Array<{ eventId: string; body: unknown }> = []
    server.use(
      http.patch('/api/care-events/:id', async ({ request, params }) => {
        requests.push({ eventId: params.id as string, body: await request.json() })
        return HttpResponse.json(careEventUpdatedFixture, { status: 200 })
      })
    )
    const { result } = renderHook(() => useCareEventMutations(5), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.updateEvent.mutateAsync({
        eventId: 50,
        payload: { note: 'Checked the soil moisture.' },
      })
    })
    await waitFor(() => expect(result.current.updateEvent.isSuccess).toBe(true))
    expect(result.current.updateEvent.data).toEqual(careEventUpdatedFixture.data)
    expect(requests[0]).toMatchObject({
      eventId: '50',
      body: { note: 'Checked the soil moisture.' },
    })
  })
})

describe('useCareEventMutations - deleteEvent', () => {
  it('deletes a care event and resolves with no body', async () => {
    let deletedId: string | undefined
    server.use(
      http.delete('/api/care-events/:id', ({ params }) => {
        deletedId = params.id as string
        return new HttpResponse(null, { status: 204 })
      })
    )
    const { result } = renderHook(() => useCareEventMutations(5), { wrapper: makeWrapper() })
    await act(async () => {
      await result.current.deleteEvent.mutateAsync(50)
    })
    await waitFor(() => expect(result.current.deleteEvent.isSuccess).toBe(true))
    expect(result.current.deleteEvent.data).toBeFalsy()
    expect(deletedId).toBe('50')
  })
})

describe('useCareEventMutations - uploadEventPhoto', () => {
  it('uploads a care-event photo and resolves the real created shape', async () => {
    const requests: Array<{ plantId: string; formData: FormData }> = []
    server.use(
      http.post('/api/plants/:id/photos', async ({ request, params }) => {
        requests.push({ plantId: params.id as string, formData: await request.formData() })
        return HttpResponse.json(photoCreatedFixture, { status: 201 })
      })
    )
    const { result } = renderHook(() => useCareEventMutations(5), { wrapper: makeWrapper() })
    const file = new File(['x'], 'capture.png', { type: 'image/png' })
    await act(async () => {
      await result.current.uploadEventPhoto.mutateAsync({ file, careEventId: 42 })
    })
    await waitFor(() => expect(result.current.uploadEventPhoto.isSuccess).toBe(true))
    expect(result.current.uploadEventPhoto.data).toEqual(photoCreatedFixture.data)
    expect(requests[0]?.plantId).toBe('5')
    // MSW's parsed File loses the original name in jsdom, so the content type
    // confirms the file landed on the right field.
    const uploadedFile = requests[0]?.formData.get('photo') as File
    expect(uploadedFile.type).toBe('image/png')
    expect(requests[0]?.formData.get('care_event_id')).toBe('42')
  })
})
