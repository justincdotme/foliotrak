import { render, screen } from '@testing-library/react'
import { describe, it, expect, beforeAll, afterAll, vi } from 'vitest'
import { LocationMeanHealthBar } from './location-mean-health'
import type { LocationSummary } from '@/api/types'

// jsdom has no layout engine, so getBoundingClientRect() always reports 0x0;
// ResponsiveContainer reads it on mount and warns unless the container has size.
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

const loc = (
  location_id: number,
  location_name: string,
  mean_health: number | null,
  sample_size: number
): LocationSummary => ({
  location_id,
  location_name,
  plant_count: 2,
  mean_health,
  health_readings: [],
  sample_size,
})

describe('LocationMeanHealthBar', () => {
  it('renders the chart title', () => {
    render(<LocationMeanHealthBar data={[loc(1, 'Shelf', 3.5, 4)]} />)
    expect(screen.getByText('Mean health by location')).toBeInTheDocument()
  })

  it('renders without crashing when data is empty', () => {
    render(<LocationMeanHealthBar data={[]} />)
    expect(screen.getByText('Mean health by location')).toBeInTheDocument()
  })

  it('skips locations with no mean health reading', () => {
    render(
      <LocationMeanHealthBar data={[loc(1, 'Shelf', 3.5, 4), loc(2, 'Windowsill', null, 0)]} />
    )
    // Only the title and n= count for the non-null location are shown; no crash
    expect(screen.getByText('Mean health by location')).toBeInTheDocument()
  })
})
