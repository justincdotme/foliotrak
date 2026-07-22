import { renderHook, waitFor } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { useSpeciesSuggest } from '@/hooks/useSpeciesSuggest'
import speciesSuggestFixture from '../../../fixtures/species/suggest.json'

describe('useSpeciesSuggest (via MSW)', () => {
  it('resolves suggestions through the real network layer', async () => {
    const { result } = renderHook(() => useSpeciesSuggest('monstera'))

    await waitFor(() => expect(result.current.loading).toBe(false), { timeout: 2000 })

    expect(result.current.results).toHaveLength(speciesSuggestFixture.data.length)
    expect(result.current.results[0]?.canonical_name).toBe(
      speciesSuggestFixture.data[0]?.canonical_name
    )
  })
})
