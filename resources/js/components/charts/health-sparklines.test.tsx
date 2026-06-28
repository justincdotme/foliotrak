import { render, screen } from '@testing-library/react'
import { describe, it, expect } from 'vitest'
import { HealthSparklines } from './health-sparklines'
import type { GroupComparison } from '@/api/types'

const cmp = (plant_id: number, common_name: string | null): GroupComparison => ({
  plant_id,
  common_name,
  health_trend: [{ date: '2026-06-01', value: 4 }],
  watering_interval_days: 7,
  fertilizer_interval_days: null,
})

describe('HealthSparklines', () => {
  it('renders the plant name for each entry', () => {
    render(<HealthSparklines comparison={[cmp(1, 'Pothos'), cmp(2, 'Monstera')]} />)
    expect(screen.getByText('Pothos')).toBeInTheDocument()
    expect(screen.getByText('Monstera')).toBeInTheDocument()
  })

  it('shows "Unnamed" for a plant with no common name', () => {
    render(<HealthSparklines comparison={[cmp(1, null)]} />)
    expect(screen.getByText('Unnamed')).toBeInTheDocument()
  })

  it('renders without crashing for an empty comparison', () => {
    render(<HealthSparklines comparison={[]} />)
    expect(document.body).toBeInTheDocument()
  })
})
