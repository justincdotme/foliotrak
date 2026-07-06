import { render, screen } from '@testing-library/react'
import { describe, it, expect, beforeAll, afterAll, vi } from 'vitest'
import { GroupComparison } from './group-comparison'
import type { GroupComparison as GroupComparisonType } from '@/api/types'

// jsdom has no layout engine, so ResponsiveContainer warns unless mocked.
beforeAll(() => {
  vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockReturnValue({
    width: 500,
    height: 300,
    top: 0,
    left: 0,
    bottom: 300,
    right: 500,
    x: 0,
    y: 0,
    toJSON: () => {},
  })
})

afterAll(() => {
  vi.restoreAllMocks()
})

describe('GroupComparison', () => {
  it('renders the chart title', () => {
    const comparison: GroupComparisonType[] = [
      {
        plant_id: 1,
        common_name: 'Monstera',
        health_trend: [{ date: '2026-01-01', value: 4 }],
        watering_interval_days: 7,
        fertilizer_interval_days: 14,
      },
    ]

    render(<GroupComparison comparison={comparison} />)
    expect(screen.getByText('Health trend across the group')).toBeInTheDocument()
  })

  it('renders the legend with plant names', () => {
    const comparison: GroupComparisonType[] = [
      {
        plant_id: 1,
        common_name: 'Monstera',
        health_trend: [{ date: '2026-01-01', value: 4 }],
        watering_interval_days: 7,
        fertilizer_interval_days: 14,
      },
      {
        plant_id: 2,
        common_name: 'Pothos',
        health_trend: [{ date: '2026-01-01', value: 3 }],
        watering_interval_days: 5,
        fertilizer_interval_days: null,
      },
      {
        plant_id: 3,
        common_name: null,
        health_trend: [{ date: '2026-01-01', value: 2 }],
        watering_interval_days: null,
        fertilizer_interval_days: null,
      },
    ]

    render(<GroupComparison comparison={comparison} />)

    expect(screen.getByText('Monstera')).toBeInTheDocument()
    expect(screen.getByText('Pothos')).toBeInTheDocument()
    expect(screen.getByText('Unnamed')).toBeInTheDocument()
  })
})
