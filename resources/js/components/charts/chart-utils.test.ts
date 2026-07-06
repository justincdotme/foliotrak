import { describe, it, expect, vi, afterEach } from 'vitest'
import {
  computeTickInterval,
  describeCorrelation,
  filterByDateRange,
  pairsToHeatmapSeries,
  prettyVar,
  regression,
} from './chart-utils'
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

  // These three use an unknown factor (pot_size) to exercise the generic strength grading
  // path; known factors route to factor-specific copy that skips the strength label.
  it.each([
    [0.3, 'a weak'],
    [0.55, 'a moderate'],
    [0.85, 'a strong'],
  ])(
    'grades strength %s as %s for a significant pair on an unknown factor',
    (correlation, label) => {
      expect(describeCorrelation(pair({ x_variable: 'pot_size', correlation }))).toContain(label)
    }
  )

  it('reads a negative coefficient as coinciding with lower outcomes, never causally', () => {
    const text = describeCorrelation(pair({ x_variable: 'pot_size', correlation: -0.6 }))
    expect(text).toContain('lower')
    expect(text).toContain('coincided with')
    expect(text).toContain('not a proven cause')
    expect(text).not.toMatch(/caused|leads to|because/i)
  })

  it('reads a positive coefficient as coinciding with higher outcomes', () => {
    expect(describeCorrelation(pair({ x_variable: 'pot_size', correlation: 0.6 }))).toContain(
      'higher'
    )
  })

  it.each<[string, number, string, string]>([
    ['watering_interval_days', 0.6, 'watered at this frequency', 'higher health readings'],
    ['watering_interval_days', -0.6, 'watered at this frequency', 'lower health readings'],
    ['ambient_humidity_pct', 0.6, 'humid', 'higher health readings'],
    ['ambient_humidity_pct', -0.6, 'humid', 'lower health readings'],
    ['light_level', 0.6, 'brighter', 'higher health readings'],
    ['light_level', -0.6, 'dimmer', 'lower health readings'],
    ['soil_moisture', 0.6, 'wetter', 'higher health readings'],
    ['soil_moisture', -0.6, 'drier', 'lower health readings'],
  ])(
    'returns factor-specific copy for %s (r=%s): includes "%s" and "%s"',
    (x_variable, correlation, phrase, direction) => {
      const text = describeCorrelation(pair({ x_variable, correlation }))
      expect(text).toContain(phrase)
      expect(text).toContain(direction)
    }
  )

  it('falls back to generic copy for an unknown factor', () => {
    const text = describeCorrelation(pair({ x_variable: 'pot_size', correlation: 0.6 }))
    expect(text).toContain('coincided with')
    expect(text).not.toMatch(/watered at|humid|brighter|dimmer|wetter|drier/i)
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

describe('regression', () => {
  it('returns null for fewer than 2 points', () => {
    expect(regression([])).toBeNull()
    expect(regression([{ x: 1, y: 2 }])).toBeNull()
  })

  it('returns null when all x values are equal', () => {
    expect(
      regression([
        { x: 3, y: 1 },
        { x: 3, y: 4 },
        { x: 3, y: 2 },
      ])
    ).toBeNull()
  })

  it('computes correct slope and intercept for a known dataset', () => {
    // (0,0), (1,2), (2,4) -> slope=2, intercept=0
    const result = regression([
      { x: 0, y: 0 },
      { x: 1, y: 2 },
      { x: 2, y: 4 },
    ])
    expect(result).not.toBeNull()
    if (!result) return
    expect(result.slope).toBeCloseTo(2)
    expect(result.intercept).toBeCloseTo(0)
  })

  it('handles a two-point line with a non-zero intercept', () => {
    // (1,1), (2,3) -> slope=2, intercept=-1
    const result = regression([
      { x: 1, y: 1 },
      { x: 2, y: 3 },
    ])
    expect(result).not.toBeNull()
    if (!result) return
    expect(result.slope).toBeCloseTo(2)
    expect(result.intercept).toBeCloseTo(-1)
  })
})

describe('computeTickInterval', () => {
  it.each([
    [1, 0],
    [4, 0],
    [8, 0],
  ])('shows every tick when data has %d points', (length, expected) => {
    expect(computeTickInterval(length)).toBe(expected)
  })

  it.each([
    [14, 1],
    [21, 2],
    [50, 7],
  ])('skips ticks when data has %d points (interval=%d)', (length, expected) => {
    expect(computeTickInterval(length)).toBe(expected)
  })
})

describe('filterByDateRange', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  const items = [
    { date: '2026-06-01', v: 1 },
    { date: '2026-06-15', v: 2 },
    { date: '2026-06-28', v: 3 },
    { date: '2026-07-03', v: 4 },
    { date: '2026-07-05', v: 5 },
  ]

  it('returns all items when range is "all"', () => {
    expect(filterByDateRange(items, i => i.date, 'all')).toEqual(items)
  })

  it('filters to the last 7 days relative to now', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-07-06T12:00:00.000Z'))

    const result = filterByDateRange(items, i => i.date, '7d')
    expect(result.map(i => i.v)).toEqual([4, 5])
  })

  it('filters to the last 30 days relative to now', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-07-06T12:00:00.000Z'))

    const result = filterByDateRange(items, i => i.date, '30d')
    expect(result.map(i => i.v)).toEqual([2, 3, 4, 5])
  })

  it('filters to the last 90 days relative to now', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-07-06T12:00:00.000Z'))

    const result = filterByDateRange(items, i => i.date, '90d')
    expect(result.map(i => i.v)).toEqual(items.map(i => i.v))
  })

  it('returns an empty array when no items fall within the range', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2027-01-01T12:00:00.000Z'))

    expect(filterByDateRange(items, i => i.date, '7d')).toEqual([])
  })

  it('works with a custom dateAccessor', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-07-06T12:00:00.000Z'))

    const records = [
      { created: '2026-05-01', id: 1 },
      { created: '2026-07-05', id: 2 },
    ]
    const result = filterByDateRange(records, r => r.created, '7d')
    expect(result.map(r => r.id)).toEqual([2])
  })
})
