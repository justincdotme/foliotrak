import { describe, it, expect } from 'vitest'
import { describeCorrelation, pairsToHeatmapSeries, prettyVar } from './chart-utils'
import type { CorrelationPair } from '@/api/types'

const pair = (over: Partial<CorrelationPair> = {}): CorrelationPair => ({
  x_variable: 'watering_interval_days',
  y_variable: 'overall_health',
  correlation: 0.5,
  p_value: 0.01,
  sample_size: 12,
  confidence_band: { lower: 0.2, upper: 0.7 },
  significant_after_fdr: true,
  points: [],
  ...over,
})

describe('describeCorrelation', () => {
  it('calls it chance when the pair is not significant after FDR, whatever the coefficient', () => {
    const text = describeCorrelation(pair({ correlation: 0.8, significant_after_fdr: false }))
    expect(text).toContain('could easily be chance')
    expect(text).not.toContain('proven')
  })

  it('calls it chance for a near-zero coefficient even when flagged significant', () => {
    expect(describeCorrelation(pair({ correlation: 0.1 }))).toContain('No clear link')
  })

  it.each([
    [0.3, 'a weak'],
    [0.55, 'a moderate'],
    [0.85, 'a strong'],
  ])('grades strength %s as %s for a significant pair', (correlation, label) => {
    expect(describeCorrelation(pair({ correlation }))).toContain(label)
  })

  it('reads a negative coefficient as coinciding with lower outcomes, never causally', () => {
    const text = describeCorrelation(pair({ correlation: -0.6 }))
    expect(text).toContain('lower')
    expect(text).toContain('coincided with')
    expect(text).toContain('not a proven cause')
    expect(text).not.toMatch(/caused|leads to|because/i)
  })

  it('reads a positive coefficient as coinciding with higher outcomes', () => {
    expect(describeCorrelation(pair({ correlation: 0.6 }))).toContain('higher')
  })
})

describe('pairsToHeatmapSeries', () => {
  it('builds a factor-by-outcome grid with a row per outcome', () => {
    const series = pairsToHeatmapSeries([
      pair({
        x_variable: 'watering_interval_days',
        y_variable: 'overall_health',
        correlation: 0.4,
      }),
      pair({ x_variable: 'light_level', y_variable: 'overall_health', correlation: -0.2 }),
    ])

    expect(series).toHaveLength(1)
    expect(series[0]?.id).toBe(prettyVar('overall_health'))
    expect(series[0]?.data.map(c => c.x)).toEqual([
      prettyVar('watering_interval_days'),
      prettyVar('light_level'),
    ])
    expect(series[0]?.data.map(c => c.y)).toEqual([0.4, -0.2])
  })

  it('leaves a null cell where a factor-outcome combination has no pair', () => {
    const series = pairsToHeatmapSeries([
      pair({ x_variable: 'watering_interval_days', y_variable: 'overall_health' }),
      pair({ x_variable: 'light_level', y_variable: 'growth_rate', correlation: 0.3 }),
    ])

    const healthRow = series.find(s => s.id === prettyVar('overall_health'))
    const missing = healthRow?.data.find(c => c.x === prettyVar('light_level'))
    expect(missing?.y).toBeNull()
    expect(missing?.n).toBeNull()
  })
})
