import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { act, renderHook } from '@testing-library/react'

vi.mock('@/api/client', () => ({ suggestSpecies: vi.fn() }))

import { suggestSpecies } from '@/api/client'
import { useSpeciesSuggest } from './useSpeciesSuggest'

describe('useSpeciesSuggest', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.mocked(suggestSpecies).mockReset()
    vi.mocked(suggestSpecies).mockResolvedValue([])
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('does not call the API below the minimum query length', () => {
    renderHook(() => useSpeciesSuggest('po'))
    act(() => {
      vi.advanceTimersByTime(500)
    })
    expect(suggestSpecies).not.toHaveBeenCalled()
  })

  it('collapses a typing burst into a single debounced call for the latest query', async () => {
    const { rerender } = renderHook(({ q }) => useSpeciesSuggest(q), {
      initialProps: { q: 'po' },
    })
    rerender({ q: 'pot' })
    rerender({ q: 'poth' })

    await act(async () => {
      vi.advanceTimersByTime(300)
    })

    expect(suggestSpecies).toHaveBeenCalledTimes(1)
    expect(suggestSpecies).toHaveBeenCalledWith('poth')
  })
})
