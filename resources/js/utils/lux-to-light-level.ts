const THRESHOLDS = [50, 100, 500, 1000, 2000, 5000, 10000, 25000, 50000] as const

export function luxToLightLevel(lux: number): number {
  if (lux <= 0) return 0
  for (let i = 0; i < THRESHOLDS.length; i++) {
    const threshold = THRESHOLDS[i]
    if (threshold !== undefined && lux < threshold) return i + 1
  }
  return 10
}
