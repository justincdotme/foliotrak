import { describe, it, expect, vi, afterEach } from 'vitest'
import { ageDays, fmtDate, fmtDateY, relDay, formatSensorLabel } from './format'

function isoAt(year: number, month: number, day: number, hour = 12): string {
  return new Date(year, month - 1, day, hour, 0, 0).toISOString()
}

afterEach(() => {
  vi.useRealTimers()
})

describe('ageDays', () => {
  it('returns 0 for a timestamp earlier today', () => {
    vi.useFakeTimers({ now: new Date(2026, 5, 30, 14, 0, 0) })
    expect(ageDays(isoAt(2026, 6, 30, 8))).toBe(0)
  })

  it('returns 1 for late yesterday even if < 24 hours ago', () => {
    vi.useFakeTimers({ now: new Date(2026, 5, 30, 8, 0, 0) })
    expect(ageDays(isoAt(2026, 6, 29, 21))).toBe(1)
  })

  it('returns 1 for early yesterday even if > 24 hours ago', () => {
    vi.useFakeTimers({ now: new Date(2026, 5, 30, 22, 0, 0) })
    expect(ageDays(isoAt(2026, 6, 29, 6))).toBe(1)
  })

  it('returns 2 for two calendar days ago', () => {
    vi.useFakeTimers({ now: new Date(2026, 5, 30, 10, 0, 0) })
    expect(ageDays(isoAt(2026, 6, 28, 20))).toBe(2)
  })
})

describe('relDay', () => {
  it('returns Today for a timestamp earlier today', () => {
    vi.useFakeTimers({ now: new Date(2026, 5, 30, 14, 0, 0) })
    expect(relDay(isoAt(2026, 6, 30, 8))).toBe('Today')
  })

  it('returns Yesterday for late last night even if < 24 hours elapsed', () => {
    vi.useFakeTimers({ now: new Date(2026, 5, 30, 8, 0, 0) })
    expect(relDay(isoAt(2026, 6, 29, 23))).toBe('Yesterday')
  })

  it('returns day count for 3 days ago', () => {
    vi.useFakeTimers({ now: new Date(2026, 5, 30, 10, 0, 0) })
    expect(relDay(isoAt(2026, 6, 27, 10))).toBe('3 days ago')
  })

  it('returns 1 week ago for 7-13 days', () => {
    vi.useFakeTimers({ now: new Date(2026, 5, 30, 10, 0, 0) })
    expect(relDay(isoAt(2026, 6, 22, 10))).toBe('1 week ago')
  })

  it('returns N weeks ago for 14+ days', () => {
    vi.useFakeTimers({ now: new Date(2026, 5, 30, 10, 0, 0) })
    expect(relDay(isoAt(2026, 6, 15, 10))).toBe('2 weeks ago')
  })
})

describe('fmtDate and fmtDateY', () => {
  afterEach(() => {
    vi.unstubAllEnvs()
  })

  // A date-only string must render as its own calendar day in every timezone;
  // UTC parsing shifted it to the previous day for viewers west of UTC.
  it.each(['America/Denver', 'UTC', 'Pacific/Auckland'])(
    'renders a date-only string on its own calendar day in %s',
    tz => {
      vi.stubEnv('TZ', tz)
      expect(fmtDate('2026-06-26')).toBe('Jun 26')
      expect(fmtDateY('2026-07-05')).toBe('Jul 5, 2026')
    }
  )

  it('converts a full timestamp to the local calendar day', () => {
    vi.stubEnv('TZ', 'America/Denver')
    expect(fmtDate('2026-06-27T01:00:00Z')).toBe('Jun 26')
  })
})

describe('formatSensorLabel', () => {
  it('appends the location when present', () => {
    expect(formatSensorLabel('Desk sensor', 'Living room')).toBe('Desk sensor - Living room')
  })

  it('returns just the name when location is null', () => {
    expect(formatSensorLabel('Desk sensor', null)).toBe('Desk sensor')
  })
})
