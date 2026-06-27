import type { CorrelationPair } from '@/api/types'

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
        light_level: 'Light level',
        fertilizer_npk_n: 'Fertilizer N',
        pot_size: 'Pot size',
        health_trend: 'Health',
        growth_rate: 'Growth',
      } as Record<string, string>
    )[v] || v.replace(/_/g, ' ')
  )
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

  const howStrong = strength < 0.4 ? 'a weak' : strength < 0.7 ? 'a moderate' : 'a strong'
  const direction = pair.correlation < 0 ? 'lower' : 'higher'
  return `More ${x} coincided with ${direction} ${y} (${howStrong} pattern). A potential factor, not a proven cause.`
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
