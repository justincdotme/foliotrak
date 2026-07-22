import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
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

function daysAgo(days: number): string {
  const d = new Date()
  d.setDate(d.getDate() - days)
  return d.toISOString().slice(0, 10)
}

async function openPlantFilter() {
  await userEvent.click(screen.getByRole('button', { name: /plants$/ }))
}

describe('GroupComparison', () => {
  it('renders the chart title', () => {
    const comparison: GroupComparisonType[] = [
      {
        plant_id: 1,
        common_name: 'Monstera',
        health_trend: [{ date: daysAgo(0), value: 4 }],
        watering_interval_days: 7,
        fertilizer_interval_days: 14,
      },
    ]

    render(<GroupComparison comparison={comparison} />)
    expect(screen.getByText('Health trend across the group')).toBeInTheDocument()
  })

  it('renders the legend with every plant selected by default', () => {
    const comparison: GroupComparisonType[] = [
      {
        plant_id: 1,
        common_name: 'Monstera',
        health_trend: [{ date: daysAgo(0), value: 4 }],
        watering_interval_days: 7,
        fertilizer_interval_days: 14,
      },
      {
        plant_id: 2,
        common_name: 'Pothos',
        health_trend: [{ date: daysAgo(0), value: 3 }],
        watering_interval_days: 5,
        fertilizer_interval_days: null,
      },
      {
        plant_id: 3,
        common_name: null,
        health_trend: [{ date: daysAgo(0), value: 2 }],
        watering_interval_days: null,
        fertilizer_interval_days: null,
      },
    ]

    render(<GroupComparison comparison={comparison} />)

    expect(screen.getByText('Monstera')).toBeInTheDocument()
    expect(screen.getByText('Pothos')).toBeInTheDocument()
    expect(screen.getByText('Unnamed')).toBeInTheDocument()
    expect(screen.getByText('3 of 3 plants')).toBeInTheDocument()
  })

  it('drops a plant from the chart and sample size once unchecked in the filter', async () => {
    const comparison: GroupComparisonType[] = [
      {
        plant_id: 1,
        common_name: 'Monstera',
        health_trend: [{ date: daysAgo(0), value: 4 }],
        watering_interval_days: 7,
        fertilizer_interval_days: 14,
      },
      {
        plant_id: 2,
        common_name: 'Pothos',
        health_trend: [{ date: daysAgo(0), value: 3 }],
        watering_interval_days: 5,
        fertilizer_interval_days: null,
      },
    ]

    render(<GroupComparison comparison={comparison} />)
    expect(screen.getByText('n = 2')).toBeInTheDocument()

    await openPlantFilter()
    await userEvent.click(screen.getByRole('button', { name: 'Pothos' }))
    await userEvent.keyboard('{Escape}')

    expect(screen.queryByText('Pothos')).not.toBeInTheDocument()
    expect(screen.getByText('Monstera')).toBeInTheDocument()
    expect(screen.getByText('n = 1')).toBeInTheDocument()
  })

  it('shows an empty state when every plant is cleared from the filter', async () => {
    const comparison: GroupComparisonType[] = [
      {
        plant_id: 1,
        common_name: 'Monstera',
        health_trend: [{ date: daysAgo(0), value: 4 }],
        watering_interval_days: 7,
        fertilizer_interval_days: 14,
      },
    ]

    render(<GroupComparison comparison={comparison} />)

    await openPlantFilter()
    await userEvent.click(screen.getByRole('button', { name: 'Clear' }))
    await userEvent.keyboard('{Escape}')

    expect(screen.getByText('No plants selected')).toBeInTheDocument()
    expect(screen.queryByText('Monstera')).not.toBeInTheDocument()
  })

  it('resets the plant selection when the incoming group of plants changes', async () => {
    const first: GroupComparisonType[] = [
      {
        plant_id: 1,
        common_name: 'Monstera',
        health_trend: [{ date: daysAgo(0), value: 4 }],
        watering_interval_days: 7,
        fertilizer_interval_days: 14,
      },
      {
        plant_id: 2,
        common_name: 'Pothos',
        health_trend: [{ date: daysAgo(0), value: 3 }],
        watering_interval_days: 5,
        fertilizer_interval_days: null,
      },
    ]

    const { rerender } = render(<GroupComparison comparison={first} />)

    await openPlantFilter()
    await userEvent.click(screen.getByRole('button', { name: 'Pothos' }))
    await userEvent.keyboard('{Escape}')
    expect(screen.getByText('n = 1')).toBeInTheDocument()

    const second: GroupComparisonType[] = [
      {
        plant_id: 3,
        common_name: 'Fiddle Leaf Fig',
        health_trend: [{ date: daysAgo(0), value: 5 }],
        watering_interval_days: 10,
        fertilizer_interval_days: null,
      },
    ]

    rerender(<GroupComparison comparison={second} />)

    expect(screen.getByText('Fiddle Leaf Fig')).toBeInTheDocument()
    expect(screen.getByText('n = 1')).toBeInTheDocument()
    expect(screen.getByText('1 of 1 plants')).toBeInTheDocument()
  })

  it('filters points outside the selected time window', async () => {
    const comparison: GroupComparisonType[] = [
      {
        plant_id: 1,
        common_name: 'Monstera',
        health_trend: [
          { date: daysAgo(800), value: 2 },
          { date: daysAgo(10), value: 3 },
          { date: daysAgo(0), value: 4 },
        ],
        watering_interval_days: 7,
        fertilizer_interval_days: 14,
      },
    ]

    render(<GroupComparison comparison={comparison} />)
    // Default window is "year": the 800-day-old point falls outside it, the other two don't.
    expect(screen.getByText('n = 2')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('radio', { name: 'Day' }))

    // "Day" excludes the 10-day-old point too, leaving only today's.
    expect(screen.getByText('n = 1')).toBeInTheDocument()
  })
})
