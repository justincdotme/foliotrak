import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { InsightsPage } from './insights'

vi.mock('@/hooks/useTags', () => ({ useTags: vi.fn() }))
vi.mock('@/hooks/useLocations', () => ({ useLocations: vi.fn() }))
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
import { useLocations } from '@/hooks/useLocations'
import { useGroupInsights } from '@/hooks/useGroupInsights'

const tags = [{ id: 5, name: 'Tropical', color: null }]
const locations = [{ id: 7, name: 'Shelf' }]

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(useTags).mockReturnValue({ data: tags, loading: false, error: null })
  vi.mocked(useLocations).mockReturnValue({ data: locations, loading: false, error: null })
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
  group_name: 'All plants',
  tag_id: null,
  tag_name: null,
  location_id: null,
  location_name: null,
  plants,
  comparison,
  correlation_pairs,
})

describe('InsightsPage', () => {
  it('loads insights immediately with no filters selected', () => {
    vi.mocked(useGroupInsights).mockReturnValue({
      data: groupData([1, 2], [cmp(1, 'A'), cmp(2, 'B')]),
      loading: false,
      error: null,
    })

    render(<InsightsPage />)

    expect(useGroupInsights).toHaveBeenCalledWith({})
    expect(screen.getByTestId('group-comparison')).toBeInTheDocument()
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

  it('shows the at-least-2-plants empty state for a single-plant group', () => {
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

  it('shows both tag and location filter rows simultaneously', () => {
    vi.mocked(useGroupInsights).mockReturnValue({
      data: groupData([1, 2], [cmp(1, 'A'), cmp(2, 'B')]),
      loading: false,
      error: null,
    })

    render(<InsightsPage />)

    expect(screen.getByText('Tropical')).toBeInTheDocument()
    expect(screen.getByText('Shelf')).toBeInTheDocument()
  })

  it('clicking a tag chip adds it as a filter', async () => {
    vi.mocked(useGroupInsights).mockReturnValue({
      data: groupData([1, 2], [cmp(1, 'A'), cmp(2, 'B')]),
      loading: false,
      error: null,
    })

    render(<InsightsPage />)

    await userEvent.click(screen.getByText('Tropical'))

    expect(useGroupInsights).toHaveBeenLastCalledWith({ tag: 5 })
  })

  it('clicking a location chip adds it as a filter alongside the tag', async () => {
    vi.mocked(useGroupInsights).mockReturnValue({
      data: groupData([1, 2], [cmp(1, 'A'), cmp(2, 'B')]),
      loading: false,
      error: null,
    })

    render(<InsightsPage />)

    await userEvent.click(screen.getByText('Tropical'))
    await userEvent.click(screen.getByText('Shelf'))

    expect(useGroupInsights).toHaveBeenLastCalledWith({ tag: 5, location: 7 })
  })

  it('clicking an active chip deselects it', async () => {
    vi.mocked(useGroupInsights).mockReturnValue({
      data: groupData([1, 2], [cmp(1, 'A'), cmp(2, 'B')]),
      loading: false,
      error: null,
    })

    render(<InsightsPage />)

    await userEvent.click(screen.getByText('Tropical'))
    await userEvent.click(screen.getByText('Tropical'))

    expect(useGroupInsights).toHaveBeenLastCalledWith({})
  })
})
