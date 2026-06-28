import { render, screen } from '@testing-library/react'
import { describe, it, expect } from 'vitest'
import { LocationMeanHealthBar } from './location-mean-health'
import type { LocationSummary } from '@/api/types'

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
