// The app's reference "now". The mock seeds history relative to this, so
// relative labels stay consistent with the seeded data.
export const NOW = new Date()
const DAY = 86400000

// Date-only strings parse as UTC midnight per the ECMAScript spec, which puts
// them on the previous calendar day for viewers west of UTC; pin them to local
// midnight so a date renders as its own calendar day in every timezone.
export function parseDate(value: string): Date {
  return /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T00:00:00`) : new Date(value)
}

export function fmtDate(value: string): string {
  return parseDate(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

export function fmtDateY(value: string): string {
  return parseDate(value).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

export function fmtTime(value: string): string {
  return new Date(value).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
}

function calendarDaysAgo(isoStr: string): number {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const then = parseDate(isoStr)
  then.setHours(0, 0, 0, 0)
  return Math.round((today.getTime() - then.getTime()) / DAY)
}

export function relDay(value: string): string {
  const days = calendarDaysAgo(value)
  if (days <= 0) return 'Today'
  if (days === 1) return 'Yesterday'
  if (days < 7) return `${days} days ago`
  if (days < 14) return '1 week ago'
  return `${Math.floor(days / 7)} weeks ago`
}

export function ageDays(isoStr: string): number {
  return calendarDaysAgo(isoStr)
}

export function formatSensorLabel(name: string, location: string | null): string {
  return location ? `${name} - ${location}` : name
}
