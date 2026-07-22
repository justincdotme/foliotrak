const pad = (n: number): string => String(n).padStart(2, '0')

const toLocalValue = (d: Date): string =>
  `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`

export const nowLocal = (): string => toLocalValue(new Date())

export const toIso = (local: string): string => new Date(local).toISOString()

export const isoToLocal = (iso: string): string => toLocalValue(new Date(iso))
