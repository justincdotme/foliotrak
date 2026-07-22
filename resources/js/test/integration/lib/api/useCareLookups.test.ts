import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http } from 'msw'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { useFertilizerForms, useNutrients, useSymptoms } from '@/hooks/useCareLookups'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'
import symptomsFixture from '../../../fixtures/lookups/symptoms.json'
import nutrientsFixture from '../../../fixtures/lookups/nutrients.json'
import fertilizerFormsFixture from '../../../fixtures/lookups/fertilizer-forms.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('useSymptoms', () => {
  it('resolves the real symptoms list shape', async () => {
    const { result } = renderHook(() => useSymptoms(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.data).toHaveLength(symptomsFixture.data.length)
    expect(result.current.data?.[0]?.label).toBe(symptomsFixture.data[0]?.label)
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.get('/api/symptoms', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => useSymptoms(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBeTruthy()
  })
})

describe('useNutrients', () => {
  it('resolves the real nutrients list shape', async () => {
    const { result } = renderHook(() => useNutrients(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.data).toHaveLength(nutrientsFixture.data.length)
    expect(result.current.data?.[0]?.nutrient_label).toBe(nutrientsFixture.data[0]?.nutrient_label)
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.get('/api/nutrients', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => useNutrients(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBeTruthy()
  })
})

describe('useFertilizerForms', () => {
  it('resolves the real fertilizer forms list shape', async () => {
    const { result } = renderHook(() => useFertilizerForms(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.data).toHaveLength(fertilizerFormsFixture.data.length)
    expect(result.current.data?.[0]?.label).toBe(fertilizerFormsFixture.data[0]?.label)
  })

  it('surfaces an error when the API fails', async () => {
    server.use(http.get('/api/fertilizer-forms', () => jsonMessage(500, 'boom')))
    const { result } = renderHook(() => useFertilizerForms(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBeTruthy()
  })
})
