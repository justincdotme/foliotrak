import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { PlantDetailPage } from './plant-detail'

vi.mock('@/hooks/usePlant', () => ({ usePlant: vi.fn() }))
vi.mock('@/hooks/usePlantPhotos', () => ({ usePlantPhotos: vi.fn() }))
vi.mock('@/hooks/useTimeline', () => ({ useTimeline: vi.fn() }))
vi.mock('@/hooks/useRecommendations', () => ({ useRecommendations: vi.fn() }))
vi.mock('@/hooks/useCareEventMutations', () => ({ useCareEventMutations: vi.fn() }))
vi.mock('@/hooks/useEquipment', () => ({
  useEquipment: () => ({ data: [], loading: false }),
}))
vi.mock('@/components/charts/timeline-overlay', () => ({
  TimelineOverlay: () => <div data-testid="overlay" />,
}))
vi.mock('@/components/charts/health-trend', () => ({
  HealthTrend: () => <div data-testid="health" />,
}))
vi.mock('@/components/charts/weight-trend', () => ({
  WeightTrend: () => <div data-testid="weight" />,
}))
vi.mock('@/components/charts/growth-trend', () => ({
  GrowthTrend: () => <div data-testid="growth" />,
}))
vi.mock('@/components/charts/activity-heatmap', () => ({
  ActivityHeatmap: () => <div data-testid="heatmap" />,
}))
vi.mock('@/components/charts/health-by-location', () => ({
  HealthByLocation: () => <div data-testid="locations" />,
}))
// Non-chart children pull their own react-query hooks; stub them so this test
// isolates the chart-gating logic (repo convention: mock at the boundary).
vi.mock('@/components/plant/edit-plant-modal', () => ({ EditPlantModal: () => null }))
vi.mock('@/components/plant/primary-photo-modal', () => ({ PrimaryPhotoModal: () => null }))
vi.mock('@/components/plant/schedule-section', () => ({
  ScheduleSection: ({ due }: { due: unknown }) => (
    <div data-testid={due != null ? 'schedule-with-due' : 'schedule-without-due'} />
  ),
}))
vi.mock('@/components/plant/timeline-item', () => ({ TimelineItem: () => null }))
vi.mock('@/components/charts/light-trend', () => ({
  LightTrend: () => <div data-testid="light" />,
}))
vi.mock('@/components/charts/leaf-size-trend', () => ({
  LeafSizeTrend: () => <div data-testid="leaf-size" />,
}))

import { usePlant } from '@/hooks/usePlant'
import { usePlantPhotos } from '@/hooks/usePlantPhotos'
import { useTimeline } from '@/hooks/useTimeline'
import { useRecommendations } from '@/hooks/useRecommendations'
import { useCareEventMutations } from '@/hooks/useCareEventMutations'

const plant = {
  id: 1,
  common_name: 'Pothos',
  scientific_name: 'Epipremnum',
  gbif_key: null,
  location: { id: 1, name: 'Shelf' },
  acquired_on: null,
  status: 'active',
  notes: null,
  watering_interval_days_override: null,
  fertilizing_interval_days_override: null,
  cover_photo_id: null,
  cover_photo: null,
  condition: { key: 'healthy', label: 'Healthy' },
  tags: [],
  equipment: [],
}

const wateringEvent = {
  id: 10,
  plant_id: 1,
  type: 'watering',
  occurred_at: '2026-06-20T08:00:00.000Z',
  note: null,
}

const setTimeline = (over: Record<string, unknown>) =>
  vi.mocked(useTimeline).mockReturnValue({
    data: {
      plant,
      events: [],
      health_trend: [],
      weight_trend: [],
      growth_trend: [],
      recommendations: [],
      photos: [],
      ...over,
    },
    loading: false,
    error: null,
  } as never)

const setRecommendations = (healthByLocation: unknown[]) =>
  vi.mocked(useRecommendations).mockReturnValue({
    data: {
      plant_id: 1,
      gate: { state: 'ready', history_days: 60, required_days: 28, days_to_go: 0 },
      watering: null,
      position_insights: [],
      health_by_location: healthByLocation,
    },
    loading: false,
    error: null,
  } as never)

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(usePlant).mockReturnValue({ data: plant, loading: false, error: null } as never)
  vi.mocked(usePlantPhotos).mockReturnValue({ data: [], loading: false, error: null } as never)
  vi.mocked(useRecommendations).mockReturnValue({
    data: null,
    loading: false,
    error: null,
  } as never)
  vi.mocked(useCareEventMutations).mockReturnValue({
    deleteEvent: { mutateAsync: vi.fn() },
  } as never)
})

const renderPage = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={qc}>
      <PlantDetailPage id={1} go={vi.fn()} openLog={vi.fn()} viewPhoto={vi.fn()} />
    </QueryClientProvider>
  )
}

describe('PlantDetailPage charts', () => {
  it('shows only the activity heatmap when there are events but no observations', () => {
    setTimeline({ events: [wateringEvent] })

    renderPage()

    expect(screen.getByTestId('heatmap')).toBeInTheDocument()
    expect(screen.queryByTestId('overlay')).not.toBeInTheDocument()
    expect(screen.queryByTestId('health')).not.toBeInTheDocument()
  })

  it('shows the overlay and health trend when health observations exist', () => {
    setTimeline({ events: [wateringEvent], health_trend: [{ date: '2026-06-20', value: 4 }] })

    renderPage()

    expect(screen.getByTestId('overlay')).toBeInTheDocument()
    expect(screen.getByTestId('health')).toBeInTheDocument()
    expect(screen.getByTestId('heatmap')).toBeInTheDocument()
  })

  it('suppresses health charts when the health series is all null, but renders weight and growth that have values', () => {
    setTimeline({
      events: [wateringEvent],
      health_trend: [{ date: '2026-06-20', value: null }],
      weight_trend: [{ date: '2026-06-20', value: 1200 }],
      growth_trend: [{ date: '2026-06-20', value: 'slow' }],
    })

    renderPage()

    expect(screen.queryByTestId('overlay')).not.toBeInTheDocument()
    expect(screen.queryByTestId('health')).not.toBeInTheDocument()
    expect(screen.getByTestId('weight')).toBeInTheDocument()
    expect(screen.getByTestId('growth')).toBeInTheDocument()
  })

  it('suppresses weight and growth when their series are all null', () => {
    setTimeline({
      events: [wateringEvent],
      health_trend: [{ date: '2026-06-20', value: 5 }],
      weight_trend: [{ date: '2026-06-20', value: null }],
      growth_trend: [{ date: '2026-06-20', value: null }],
    })

    renderPage()

    expect(screen.getByTestId('health')).toBeInTheDocument()
    expect(screen.queryByTestId('weight')).not.toBeInTheDocument()
    expect(screen.queryByTestId('growth')).not.toBeInTheDocument()
  })

  it('shows the empty state when there are no care events', () => {
    setTimeline({ events: [] })

    renderPage()

    expect(screen.getByText('Nothing to chart yet')).toBeInTheDocument()
    expect(screen.queryByTestId('heatmap')).not.toBeInTheDocument()
  })
})

describe('PlantDetailPage health by location', () => {
  let locId = 100
  const bucket = (name: string, sample_size: number) => ({
    location: { id: locId++, name },
    median_health: 4,
    sample_size,
    healths: Array(sample_size).fill(4),
  })

  it('renders the location comparison once two or more spots have readings', () => {
    setTimeline({ events: [wateringEvent] })
    setRecommendations([bucket('Office', 2), bucket('Kitchen', 3)])

    renderPage()

    expect(screen.getByTestId('locations')).toBeInTheDocument()
  })

  it('hides the location comparison with only one spot', () => {
    setTimeline({ events: [wateringEvent] })
    setRecommendations([bucket('Office', 4)])

    renderPage()

    expect(screen.queryByTestId('locations')).not.toBeInTheDocument()
  })

  it('ignores spots with no readings when deciding to show the comparison', () => {
    setTimeline({ events: [wateringEvent] })
    setRecommendations([bucket('Office', 3), bucket('Hallway', 0)])

    renderPage()

    expect(screen.queryByTestId('locations')).not.toBeInTheDocument()
  })
})

describe('PlantDetailPage light and leaf-size trend gating', () => {
  it('shows LightTrend when light_trend has a real value', () => {
    setTimeline({ events: [wateringEvent], light_trend: [{ date: '2026-06-20', value: 7 }] })

    renderPage()

    expect(screen.getByTestId('light')).toBeInTheDocument()
  })

  it('suppresses LightTrend when light_trend values are all null', () => {
    setTimeline({ events: [wateringEvent], light_trend: [{ date: '2026-06-20', value: null }] })

    renderPage()

    expect(screen.queryByTestId('light')).not.toBeInTheDocument()
  })

  it('shows LeafSizeTrend when leaf_size_trend has a real value', () => {
    setTimeline({ events: [wateringEvent], leaf_size_trend: [{ date: '2026-06-20', value: 45 }] })

    renderPage()

    expect(screen.getByTestId('leaf-size')).toBeInTheDocument()
  })

  it('suppresses LeafSizeTrend when leaf_size_trend values are all null', () => {
    setTimeline({
      events: [wateringEvent],
      leaf_size_trend: [{ date: '2026-06-20', value: null }],
    })

    renderPage()

    expect(screen.queryByTestId('leaf-size')).not.toBeInTheDocument()
  })
})

describe('PlantDetailPage due signal wiring', () => {
  const wateringDue = {
    status: 'overdue' as const,
    due_date: '2026-06-15',
    type: 'watering' as const,
    daysLeft: -3,
    interval: 7,
  }

  it('passes the watering due entry to ScheduleSection when due_for_care has one', () => {
    setTimeline({ events: [wateringEvent], due_for_care: [wateringDue] })

    renderPage()

    expect(screen.getByTestId('schedule-with-due')).toBeInTheDocument()
  })

  it('passes null due to ScheduleSection when due_for_care is empty', () => {
    setTimeline({ events: [wateringEvent], due_for_care: [] })

    renderPage()

    expect(screen.getByTestId('schedule-without-due')).toBeInTheDocument()
  })

  it('passes null due when due_for_care contains only a fertilizing entry', () => {
    setTimeline({
      events: [wateringEvent],
      due_for_care: [{ ...wateringDue, type: 'fertilizing' as const }],
    })

    renderPage()

    expect(screen.getByTestId('schedule-without-due')).toBeInTheDocument()
  })
})
