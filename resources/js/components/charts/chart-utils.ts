import type { CorrelationPair, CorrelationPoint } from '@/api/types'
import { HEALTH_VAR } from '@/lib/domain'

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

export function fillFromHealth(y: number): string {
  const key = Math.min(5, Math.max(1, Math.round(y)))
  return HEALTH_VAR[key] ?? 'var(--primary)'
}

export interface HeatCell {
  x: string
  y: number | null
  n: number | null
  significant: boolean
}

// Reshape the flat pair list into a factor-by-outcome grid for the Nivo heatmap. A pair missing
// from the grid leaves a null cell, which the heatmap renders as the empty color.
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
