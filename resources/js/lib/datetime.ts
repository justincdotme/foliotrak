const pad = (n: number): string => String(n).padStart(2, '0')

const toLocalValue = (d: Date): string =>
  `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`

// Logging happens after the fact, so a new entry defaults to this morning rather
// than the current minute; the user adjusts the time when it matters.
export const nowLocal = (): string => {
  const d = new Date()
  d.setHours(8, 0, 0, 0)
  return toLocalValue(d)
}

export const toIso = (local: string): string => new Date(local).toISOString()

export const isoToLocal = (iso: string): string => toLocalValue(new Date(iso))
