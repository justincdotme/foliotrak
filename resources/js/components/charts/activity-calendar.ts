import type { CareEvent } from '@/api/types'

const DAY = 86400000

const dayKey = (iso: string): string => iso.split('T')[0] ?? iso

const toYmd = (d: Date): string => d.toISOString().split('T')[0] as string

export function eventsToCalendar(events: CareEvent[]): Array<{ day: string; value: number }> {
  const counts = new Map<string, number>()
  for (const e of events) {
    const k = dayKey(e.occurred_at)
    counts.set(k, (counts.get(k) ?? 0) + 1)
  }
  return [...counts.entries()].map(([day, value]) => ({ day, value }))
}

// Rolling 60-day window keeps the calendar dense for the short histories a
// household tracker holds.
export function calendarRange(now: Date = new Date()): { from: string; to: string } {
  return { from: toYmd(new Date(now.getTime() - 60 * DAY)), to: toYmd(now) }
}
