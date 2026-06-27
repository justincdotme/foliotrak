import { render, screen } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { InsightsPage } from './insights'

vi.mock('@/hooks/useTags', () => ({ useTags: vi.fn() }))
vi.mock('@/hooks/useGroupInsights', () => ({ useGroupInsights: vi.fn() }))
vi.mock('@/components/charts/group-comparison', () => ({
  GroupComparison: () => <div data-testid="group-comparison" />,
}))
vi.mock('@/components/charts/correlation-scatter', () => ({
  CorrelationScatter: () => <div data-testid="corr-scatter" />,
}))
vi.mock('@/components/charts/correlation-heatmap', () => ({
  CorrelationHeatmap: () => <div data-testid="corr-heatmap" />,
}))
import { useTags } from '@/hooks/useTags'
import { useGroupInsights } from '@/hooks/useGroupInsights'

// First live tag id is deliberately not 1, to pin "default to the first tag".
const tags = [{ id: 5, name: 'Living room', color: null }]

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(useTags).mockReturnValue({ data: tags, loading: false, error: null })
})

const trend = [{ date: '2026-06-01', value: 4 }]
const cmp = (plant_id: number, common_name: string) => ({
  plant_id,
  common_name,
  health_trend: trend,
  watering_interval_days: 7,
  fertilizer_interval_days: null,
})

const pair = (x_variable: string) => ({
  x_variable,
  y_variable: 'overall_health',
  correlation: 0.4,
  p_value: 0.05,
  sample_size: 12,
  confidence_band: { lower: 0.1, upper: 0.7 },
  significant_after_fdr: false,
  points: [],
})

const groupData = (
  plants: number[],
  comparison: ReturnType<typeof cmp>[],
  correlation_pairs: ReturnType<typeof pair>[] = []
) => ({
  tag_id: 5,
  tag_name: 'Living room',
  plants,
  comparison,
  correlation_pairs,
})

describe('InsightsPage', () => {
  it('defaults to the first live tag, not a hardcoded id', () => {
    vi.mocked(useGroupInsights).mockReturnValue({
      data: groupData([1, 2], [cmp(1, 'A'), cmp(2, 'B')]),
      loading: false,
      error: null,
    })

    render(<InsightsPage />)

    expect(useGroupInsights).toHaveBeenCalledWith(5)
  })

  it('renders the group comparison and the correlation pre-gate panel for >= 2 plants', () => {
    vi.mocked(useGroupInsights).mockReturnValue({
      data: groupData([1, 2], [cmp(1, 'A'), cmp(2, 'B')]),
      loading: false,
      error: null,
    })

    render(<InsightsPage />)

    expect(screen.getByTestId('group-comparison')).toBeInTheDocument()
    expect(screen.getByText('Correlation analysis is coming')).toBeInTheDocument()
  })

  it('renders a scatter and no matrix for a single correlation pair', () => {
    vi.mocked(useGroupInsights).mockReturnValue({
      data: groupData([1, 2], [cmp(1, 'A'), cmp(2, 'B')], [pair('watering_interval_days')]),
      loading: false,
      error: null,
    })

    render(<InsightsPage />)

    expect(screen.getByTestId('corr-scatter')).toBeInTheDocument()
    expect(screen.queryByTestId('corr-heatmap')).not.toBeInTheDocument()
    expect(screen.queryByText('Correlation analysis is coming')).not.toBeInTheDocument()
  })

  it('adds the matrix once two or more factors are present', () => {
    vi.mocked(useGroupInsights).mockReturnValue({
      data: groupData(
        [1, 2],
        [cmp(1, 'A'), cmp(2, 'B')],
        [pair('watering_interval_days'), pair('light_level')]
      ),
      loading: false,
      error: null,
    })

    render(<InsightsPage />)

    expect(screen.getByTestId('corr-heatmap')).toBeInTheDocument()
    expect(screen.getAllByTestId('corr-scatter')).toHaveLength(2)
  })

  it('shows the at-least-2-plants empty state for a single-plant tag', () => {
    vi.mocked(useGroupInsights).mockReturnValue({
      data: groupData([1], [cmp(1, 'A')]),
      loading: false,
      error: null,
    })

    render(<InsightsPage />)

    expect(screen.getByText('Need at least 2 plants')).toBeInTheDocument()
    expect(screen.queryByTestId('group-comparison')).not.toBeInTheDocument()
  })

  it('shows an error state instead of an endless spinner when the group fetch fails', () => {
    vi.mocked(useGroupInsights).mockReturnValue({
      data: null,
      loading: false,
      error: new Error('boom'),
    })

    render(<InsightsPage />)

    expect(screen.getByText('Unable to load insights')).toBeInTheDocument()
  })

  it('shows a no-tags state when no tags exist', () => {
    vi.mocked(useTags).mockReturnValue({ data: [], loading: false, error: null })
    vi.mocked(useGroupInsights).mockReturnValue({ data: null, loading: true, error: null })

    render(<InsightsPage />)

    expect(screen.getByText('No tags yet')).toBeInTheDocument()
  })
})
