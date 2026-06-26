import { describe, it, expect } from 'vitest'
import { calendarRange, eventsToCalendar } from './activity-calendar'
import type { CareEvent } from '@/api/types'

const ev = (id: number, occurred_at: string): CareEvent =>
  ({ id, occurred_at, type: 'watering' }) as CareEvent

describe('eventsToCalendar', () => {
  it('counts events per calendar day from the ISO timestamp', () => {
    const out = eventsToCalendar([
      ev(1, '2026-06-20T08:00:00.000Z'),
      ev(2, '2026-06-20T19:30:00.000Z'),
      ev(3, '2026-06-21T08:00:00.000Z'),
    ])

    expect(out).toContainEqual({ day: '2026-06-20', value: 2 })
    expect(out).toContainEqual({ day: '2026-06-21', value: 1 })
  })

  it('returns an empty array for no events', () => {
    expect(eventsToCalendar([])).toEqual([])
  })
})

describe('calendarRange', () => {
  it('spans 60 days ending on the given day', () => {
    const { from, to } = calendarRange(new Date('2026-06-26T12:00:00.000Z'))

    expect(to).toBe('2026-06-26')
    expect(from).toBe('2026-04-27')
  })
})
