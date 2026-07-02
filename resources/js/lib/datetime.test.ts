import { describe, it, expect, vi, afterEach } from 'vitest'
import { nowLocal } from './datetime'

afterEach(() => {
  vi.useRealTimers()
})

describe('nowLocal', () => {
  // Reflects the current minute (not a fixed 8 AM) and zero-pads each field for
  // the datetime-local input.
  it.each([
    { now: new Date(2026, 6, 2, 14, 37, 0), expected: '2026-07-02T14:37' },
    { now: new Date(2026, 0, 5, 9, 3, 0), expected: '2026-01-05T09:03' },
  ])('returns the current local minute ($expected)', ({ now, expected }) => {
    vi.useFakeTimers({ now })
    expect(nowLocal()).toBe(expected)
  })
})
