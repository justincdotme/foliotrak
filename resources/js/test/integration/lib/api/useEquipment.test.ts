import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import React from 'react'
import { describe, expect, it } from 'vitest'
import { useEquipment } from '@/hooks/useEquipment'
import equipmentFixture from '../../../fixtures/lookups/equipment.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: qc }, children)
  }
}

describe('useEquipment', () => {
  it('resolves the real equipment list shape', async () => {
    const { result } = renderHook(() => useEquipment(), { wrapper: makeWrapper() })
    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.data).toHaveLength(equipmentFixture.data.length)
    expect(result.current.data?.[0]?.label).toBe(equipmentFixture.data[0]?.label)
  })
})
