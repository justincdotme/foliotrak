import type { CorrelationPair, CorrelationPoint } from '@/api/types'
import { HEALTH_VAR } from '@/lib/domain'
import { parseDate } from '@/lib/format'

export type DateRange = '7d' | '30d' | '90d' | 'all'

export const DATE_RANGE_OPTIONS: Array<{ value: string; label: string; dusk?: string }> = [
  { value: '7d', label: '7 d', dusk: 'chart-range-7d' },
  { value: '30d', label: '30 d', dusk: 'chart-range-30d' },
  { value: '90d', label: '90 d', dusk: 'chart-range-90d' },
  { value: 'all', label: 'All', dusk: 'chart-range-all' },
]

const RANGE_DAYS: Record<DateRange, number | null> = {
  '7d': 7,
  '30d': 30,
  '90d': 90,
  all: null,
}

export function filterByDateRange<T>(
  data: T[],
  dateAccessor: (item: T) => string,
  range: DateRange
): T[] {
  const days = RANGE_DAYS[range]
  if (days == null) return data
  const cutoff = new Date()
  cutoff.setDate(cutoff.getDate() - days)
  // Date-only points sit at local midnight, so a time-of-day cutoff would
  // silently drop the oldest day of the range.
  cutoff.setHours(0, 0, 0, 0)
  return data.filter(item => parseDate(dateAccessor(item)) >= cutoff)
}

export type GroupChartWindow = 'day' | 'week' | 'month' | 'year'

export const GROUP_CHART_WINDOW_OPTIONS: Array<{ value: string; label: string; dusk?: string }> = [
  { value: 'day', label: 'Day', dusk: 'group-window-day' },
  { value: 'week', label: 'Week', dusk: 'group-window-week' },
  { value: 'month', label: 'Month', dusk: 'group-window-month' },
  { value: 'year', label: 'Year', dusk: 'group-window-year' },
]

const GROUP_CHART_WINDOW_DAYS: Record<GroupChartWindow, number> = {
  day: 1,
  week: 7,
  month: 30,
  year: 365,
}

export function filterByWindow<T>(
  data: T[],
  dateAccessor: (item: T) => string,
  window: GroupChartWindow
): T[] {
  const cutoff = new Date()
  cutoff.setDate(cutoff.getDate() - GROUP_CHART_WINDOW_DAYS[window])
  cutoff.setHours(0, 0, 0, 0)
  return data.filter(item => parseDate(dateAccessor(item)) >= cutoff)
}

// Keeps tick labels from overlapping by skipping intermediate ticks when the
// dataset is wider than ~8 points.
export function computeTickInterval(dataLength: number): number {
  if (dataLength <= 8) return 0
  return Math.ceil(dataLength / 7) - 1
}

export const axis = {
  stroke: 'var(--border-strong)',
  tick: { fontSize: 11, fill: 'var(--text-subtle)' },
  tickLine: false,
}

export function prettyVar(v: string): string {
  return (
    (
      {
        watering_interval_days: 'Days between waterings',
        overall_health: 'Health',
        watering_frequency: 'Watering frequency',
        ambient_humidity_pct: 'Ambient humidity',
        light_level: 'Light level',
        soil_moisture: 'Soil moisture',
        fertilizer_npk_n: 'Fertilizer N',
        pot_size: 'Pot size',
        health_trend: 'Health',
        growth_rate: 'Growth',
      } as Record<string, string>
    )[v] || v.replace(/_/g, ' ')
  )
}

type FactorFn = (positive: boolean) => string

const FACTOR_LANGUAGE: Record<string, FactorFn> = {
  watering_interval_days: pos =>
    `Plants watered at this frequency tended toward ${pos ? 'higher' : 'lower'} health readings.`,
  ambient_humidity_pct: pos =>
    `Plants in ${pos ? 'more' : 'less'} humid conditions tended toward ${pos ? 'higher' : 'lower'} health readings.`,
  light_level: pos =>
    `Plants in ${pos ? 'brighter' : 'dimmer'} spots tended toward ${pos ? 'higher' : 'lower'} health readings.`,
  soil_moisture: pos =>
    `Plants with ${pos ? 'wetter' : 'drier'} soil tended toward ${pos ? 'higher' : 'lower'} health readings.`,
}

// Plain-language read of a correlation pair, kept strictly non-causal. The raw coefficient and
// band are shown elsewhere as quiet subtext; this is the headline a non-statistician reads.
export function describeCorrelation(pair: CorrelationPair): string {
  const x = prettyVar(pair.x_variable).toLowerCase()
  const y = prettyVar(pair.y_variable).toLowerCase()
  const strength = Math.abs(pair.correlation)

  if (!pair.significant_after_fdr || strength < 0.2) {
    return `No clear link between ${x} and ${y} yet. What is here could easily be chance.`
  }

  const factorFn = FACTOR_LANGUAGE[pair.x_variable]
  if (factorFn) return factorFn(pair.correlation > 0)

  const howStrong = strength < 0.4 ? 'a weak' : strength < 0.7 ? 'a moderate' : 'a strong'
  const direction = pair.correlation < 0 ? 'lower' : 'higher'
  return `More ${x} coincided with ${direction} ${y} (${howStrong} pattern). A potential factor, not a proven cause.`
}

export function regression(
  points: CorrelationPoint[]
): { slope: number; intercept: number } | null {
  if (points.length < 2) return null
  const n = points.length
  const sumX = points.reduce((s, p) => s + p.x, 0)
  const sumY = points.reduce((s, p) => s + p.y, 0)
  const sumXY = points.reduce((s, p) => s + p.x * p.y, 0)
  const sumX2 = points.reduce((s, p) => s + p.x * p.x, 0)
  const denom = n * sumX2 - sumX * sumX
  if (denom === 0) return null
  const slope = (n * sumXY - sumX * sumY) / denom
  const intercept = (sumY - slope * sumX) / n
  return { slope, intercept }
}

export function fillFromHealth(y: number, fallback = 'var(--primary)'): string {
  const key = Math.min(5, Math.max(1, Math.round(y)))
  return HEALTH_VAR[key] ?? fallback
}

export interface HeatCell {
  x: string
  y: number | null
  n: number | null
  significant: boolean
}

// Missing factor-outcome combinations produce null cells so the heatmap renders them as empty.
export function pairsToHeatmapSeries(pairs: CorrelationPair[]): { id: string; data: HeatCell[] }[] {
  const xs = [...new Set(pairs.map(p => p.x_variable))]
  const ys = [...new Set(pairs.map(p => p.y_variable))]

  return ys.map(yv => ({
    id: prettyVar(yv),
    data: xs.map(xv => {
      const pair = pairs.find(p => p.x_variable === xv && p.y_variable === yv)
      return {
        x: prettyVar(xv),
        y: pair ? pair.correlation : null,
        n: pair ? pair.sample_size : null,
        significant: pair ? pair.significant_after_fdr : false,
      }
    }),
  }))
}
