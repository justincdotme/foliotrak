import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { Plant, PlantRecommendations, WateringRecommendation } from '@/api/types'
import { ScheduleSection } from './schedule-section'

vi.mock('@/hooks/usePlantMutations', () => ({ useUpdatePlant: vi.fn() }))
import { useUpdatePlant } from '@/hooks/usePlantMutations'

const mutateAsync = vi.fn().mockResolvedValue({})

const plant = (overrides: Partial<Plant> = {}): Plant =>
  ({
    id: 7,
    common_name: 'Pothos',
    status: 'active',
    watering_interval_days_override: null,
    fertilizing_interval_days_override: null,
    ...overrides,
  }) as unknown as Plant

const watering = (over: Partial<WateringRecommendation> = {}): WateringRecommendation => ({
  interval_days: 7,
  amount_ml: 200,
  sample_size: 8,
  health_sample_size: 3,
  basis: 'stable',
  baseline_interval_days: null,
  recent_interval_days: null,
  rationale: 'About every 7 days, from 8 waterings.',
  computed_at: '2026-06-27T00:00:00.000Z',
  ...over,
})

const recs = (over: Partial<PlantRecommendations> = {}): PlantRecommendations => ({
  plant_id: 7,
  gate: { state: 'ready', history_days: 60, required_days: 28, days_to_go: 0 },
  watering: null,
  position_insights: [],
  health_by_location: [],
  symptom_episodes: [],
  ...over,
})

const openRecommended = async () =>
  userEvent.click(screen.getByRole('tab', { name: /recommended/i }))

beforeEach(() => {
  mutateAsync.mockClear()
  vi.mocked(useUpdatePlant).mockReturnValue({
    mutateAsync,
    isPending: false,
  } as unknown as ReturnType<typeof useUpdatePlant>)
})

describe('ScheduleSection My schedule', () => {
  it('saves watering only when fertilizing is empty', async () => {
    render(<ScheduleSection plant={plant()} recommendations={null} due={null} />)

    await userEvent.click(screen.getByRole('button', { name: /set a schedule/i }))
    await userEvent.type(screen.getByPlaceholderText('e.g. 5'), '6')
    await userEvent.click(screen.getByRole('button', { name: /save schedule/i }))

    expect(mutateAsync).toHaveBeenCalledWith({
      watering_interval_days_override: 6,
      fertilizing_interval_days_override: null,
    })
  })

  it('saves both watering and fertilizing when both are filled', async () => {
    render(<ScheduleSection plant={plant()} recommendations={null} due={null} />)

    await userEvent.click(screen.getByRole('button', { name: /set a schedule/i }))
    const inputs = screen.getAllByRole('spinbutton')
    await userEvent.type(inputs[0] as HTMLInputElement, '5')
    await userEvent.type(inputs[1] as HTMLInputElement, '14')
    await userEvent.click(screen.getByRole('button', { name: /save schedule/i }))

    expect(mutateAsync).toHaveBeenCalledWith({
      watering_interval_days_override: 5,
      fertilizing_interval_days_override: 14,
    })
  })

  it('saves fertilizing only when watering is empty', async () => {
    render(<ScheduleSection plant={plant()} recommendations={null} due={null} />)

    await userEvent.click(screen.getByRole('button', { name: /set a schedule/i }))
    const inputs = screen.getAllByRole('spinbutton')
    await userEvent.type(inputs[1] as HTMLInputElement, '21')
    await userEvent.click(screen.getByRole('button', { name: /save schedule/i }))

    expect(mutateAsync).toHaveBeenCalledWith({
      watering_interval_days_override: null,
      fertilizing_interval_days_override: 21,
    })
  })

  it('clears both overrides when both fields are empty', async () => {
    render(
      <ScheduleSection
        plant={plant({
          watering_interval_days_override: 7,
          fertilizing_interval_days_override: 28,
        })}
        recommendations={null}
        due={null}
      />
    )

    await userEvent.click(screen.getByRole('button', { name: /edit schedule/i }))
    const inputs = screen.getAllByRole('spinbutton')
    await userEvent.clear(inputs[0] as HTMLInputElement)
    await userEvent.clear(inputs[1] as HTMLInputElement)
    await userEvent.click(screen.getByRole('button', { name: /save schedule/i }))

    expect(mutateAsync).toHaveBeenCalledWith({
      watering_interval_days_override: null,
      fertilizing_interval_days_override: null,
    })
  })
})

describe('ScheduleSection Recommended', () => {
  it('shows the keep-logging countdown under the four-week gate', async () => {
    render(
      <ScheduleSection
        plant={plant()}
        recommendations={recs({
          gate: { state: 'countdown', history_days: 18, required_days: 28, days_to_go: 10 },
        })}
        due={null}
      />
    )

    await openRecommended()

    expect(screen.getByText(/keep logging/i)).toBeInTheDocument()
    expect(screen.getByText(/10 days remaining/i)).toBeInTheDocument()
    expect(screen.getByText(/18 of 28 days of history/i)).toBeInTheDocument()
  })

  it('prompts for a health reading past the gate when no observations exist', async () => {
    render(
      <ScheduleSection
        plant={plant()}
        recommendations={recs({
          gate: { state: 'no_health_data', history_days: 60, required_days: 28, days_to_go: 0 },
        })}
        due={null}
      />
    )

    await openRecommended()

    expect(screen.getByText(/add a health reading/i)).toBeInTheDocument()
  })

  it('renders the watering recommendation with its rationale and adopts it into the override', async () => {
    render(
      <ScheduleSection
        plant={plant()}
        recommendations={recs({ watering: watering({ interval_days: 6 }) })}
        due={null}
      />
    )

    await openRecommended()

    expect(screen.getByText(/water about every/i)).toBeInTheDocument()
    expect(screen.getByText(/from 8 waterings/i)).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /use this for my schedule/i }))

    expect(mutateAsync).toHaveBeenCalledWith({ watering_interval_days_override: 6 })
  })

  it('hides the adopt action when the override already matches the recommendation', async () => {
    render(
      <ScheduleSection
        plant={plant({ watering_interval_days_override: 7 })}
        recommendations={recs({ watering: watering({ interval_days: 7 }) })}
        due={null}
      />
    )

    await openRecommended()

    expect(
      screen.queryByRole('button', { name: /use this for my schedule/i })
    ).not.toBeInTheDocument()
  })

  it('reports too few waterings when the gate is open but no cadence could be derived', async () => {
    render(
      <ScheduleSection plant={plant()} recommendations={recs({ watering: null })} due={null} />
    )

    await openRecommended()

    expect(screen.getByText(/not enough waterings logged yet/i)).toBeInTheDocument()
  })

  it('shows a calm unavailable state instead of spinning forever when the fetch errors', async () => {
    render(
      <ScheduleSection plant={plant()} recommendations={null} recommendationsError due={null} />
    )

    await openRecommended()

    expect(screen.getByText(/could not load/i)).toBeInTheDocument()
  })

  it('does not show the unavailable state while the fetch is still pending', async () => {
    render(
      <ScheduleSection plant={plant()} recommendations={null} recommendationsLoading due={null} />
    )

    await openRecommended()

    expect(screen.queryByText(/could not load/i)).not.toBeInTheDocument()
  })
})
