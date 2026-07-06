import { render, screen } from '@testing-library/react'
import { describe, it, expect, beforeAll, afterAll, vi } from 'vitest'
import { HealthByLocation } from './health-by-location'
import type { LocationHealth } from '@/api/types'

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

describe('HealthByLocation', () => {
  it('renders the chart title', () => {
    const data: LocationHealth[] = [
      {
        location: { id: 1, name: 'Living Room' },
        sample_size: 5,
        median_health: 4,
        healths: [3, 4, 4, 5, 4],
      },
    ]

    render(<HealthByLocation data={data} />)
    expect(screen.getByText('Health by location')).toBeInTheDocument()
  })

  it('renders the legend with Reading and Median labels', () => {
    const data: LocationHealth[] = [
      {
        location: { id: 1, name: 'Living Room' },
        sample_size: 5,
        median_health: 4,
        healths: [3, 4, 4, 5, 4],
      },
      {
        location: { id: 2, name: 'Bedroom' },
        sample_size: 3,
        median_health: 3,
        healths: [2, 3, 4],
      },
    ]

    render(<HealthByLocation data={data} />)

    expect(screen.getByText('Reading')).toBeInTheDocument()
    expect(screen.getByText('Median')).toBeInTheDocument()
  })
})
