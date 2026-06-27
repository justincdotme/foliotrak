import { render, screen } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { DashboardPage } from './dashboard'
import type { DashboardData } from '@/api/types'

vi.mock('@/hooks/useDashboard', () => ({ useDashboard: vi.fn() }))
import { useDashboard } from '@/hooks/useDashboard'

const data: DashboardData = {
  user: { id: 1, name: 'Justin Tester', email: 'j@home.lan', pushover_user_key: null },
  due_for_care: [
    {
      plant_id: 1,
      common_name: 'Pothos',
      scientific_name: 'Epipremnum',
      status: 'overdue',
      due_date: '2026-06-20',
      type: 'watering',
      daysLeft: -3,
      interval: 7,
    },
    {
      plant_id: 2,
      common_name: 'Monstera',
      scientific_name: 'Monstera deliciosa',
      status: 'ok',
      due_date: '2026-07-01',
      type: 'watering',
      daysLeft: 5,
      interval: 9,
    },
  ],
  recent_activity: [
    {
      event_id: 9,
      plant_id: 1,
      plant_common_name: 'Pothos',
      type: 'watering',
      occurred_at: '2026-06-25T08:00:00.000Z',
      note: 'deep soak',
    },
  ],
  flagged_problems: [
    {
      plant_id: 1,
      common_name: 'Pothos',
      problems: [{ problem: 'Root rot reported', severity: 'alert' as const }],
    },
  ],
}

beforeEach(() => vi.clearAllMocks())

describe('DashboardPage', () => {
  it('renders due-for-care with overdue framing and the attention count', () => {
    vi.mocked(useDashboard).mockReturnValue({ data, loading: false, error: null })

    render(<DashboardPage go={vi.fn()} />)

    expect(screen.getByText('1 plants need attention today.')).toBeInTheDocument()
    expect(screen.getByText('3d overdue')).toBeInTheDocument()
    expect(screen.getByText('in 5d')).toBeInTheDocument()
    expect(screen.getByText('Root rot reported')).toBeInTheDocument()
    expect(screen.getByText('deep soak')).toBeInTheDocument()
  })

  it('shows the today label and counts a plant due for two care types only once', () => {
    const base = {
      plant_id: 1,
      common_name: 'Pothos',
      scientific_name: 'Epipremnum',
      due_date: '2026-06-20',
      interval: 7,
    }
    const both: DashboardData = {
      ...data,
      due_for_care: [
        { ...base, type: 'watering', status: 'overdue', daysLeft: -2 },
        { ...base, type: 'fertilizing', status: 'due-soon', daysLeft: 0 },
      ],
    }
    vi.mocked(useDashboard).mockReturnValue({ data: both, loading: false, error: null })

    render(<DashboardPage go={vi.fn()} />)

    expect(screen.getByText('today')).toBeInTheDocument()
    expect(screen.getByText('2d overdue')).toBeInTheDocument()
    expect(screen.getByText('1 plants need attention today.')).toBeInTheDocument()
  })

  it('shows the caught-up empty states when nothing is due', () => {
    vi.mocked(useDashboard).mockReturnValue({
      data: { ...data, due_for_care: [], flagged_problems: [], recent_activity: [] },
      loading: false,
      error: null,
    })

    render(<DashboardPage go={vi.fn()} />)

    expect(screen.getByText('All caught up')).toBeInTheDocument()
    expect(screen.getByText('Looking good')).toBeInTheDocument()
    expect(screen.getByText('No activity yet')).toBeInTheDocument()
  })
})
