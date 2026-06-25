import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { Plant } from '@/api/types'
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

beforeEach(() => {
  mutateAsync.mockClear()
  vi.mocked(useUpdatePlant).mockReturnValue({
    mutateAsync,
    isPending: false,
  } as unknown as ReturnType<typeof useUpdatePlant>)
})

describe('ScheduleSection My schedule', () => {
  it('saves watering only when fertilizing is empty', async () => {
    render(<ScheduleSection plant={plant()} recs={[]} due={null} events={[]} />)

    await userEvent.click(screen.getByRole('button', { name: /set a schedule/i }))
    await userEvent.type(screen.getByPlaceholderText('e.g. 5'), '6')
    await userEvent.click(screen.getByRole('button', { name: /save schedule/i }))

    expect(mutateAsync).toHaveBeenCalledWith({
      watering_interval_days_override: 6,
      fertilizing_interval_days_override: null,
    })
  })

  it('saves both watering and fertilizing when both are filled', async () => {
    render(<ScheduleSection plant={plant()} recs={[]} due={null} events={[]} />)

    await userEvent.click(screen.getByRole('button', { name: /set a schedule/i }))
    const inputs = screen.getAllByRole('spinbutton')
    const waterInput = inputs[0] as HTMLInputElement
    const fertInput = inputs[1] as HTMLInputElement
    await userEvent.type(waterInput, '5')
    await userEvent.type(fertInput, '14')
    await userEvent.click(screen.getByRole('button', { name: /save schedule/i }))

    expect(mutateAsync).toHaveBeenCalledWith({
      watering_interval_days_override: 5,
      fertilizing_interval_days_override: 14,
    })
  })

  it('saves fertilizing only when watering is empty', async () => {
    render(<ScheduleSection plant={plant()} recs={[]} due={null} events={[]} />)

    await userEvent.click(screen.getByRole('button', { name: /set a schedule/i }))
    const inputs = screen.getAllByRole('spinbutton')
    const fertInput = inputs[1] as HTMLInputElement
    await userEvent.type(fertInput, '21')
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
        recs={[]}
        due={null}
        events={[]}
      />
    )

    await userEvent.click(screen.getByRole('button', { name: /edit schedule/i }))
    const inputs = screen.getAllByRole('spinbutton')
    const waterInput = inputs[0] as HTMLInputElement
    const fertInput = inputs[1] as HTMLInputElement
    await userEvent.clear(waterInput)
    await userEvent.clear(fertInput)
    await userEvent.click(screen.getByRole('button', { name: /save schedule/i }))

    expect(mutateAsync).toHaveBeenCalledWith({
      watering_interval_days_override: null,
      fertilizing_interval_days_override: null,
    })
  })
})
