import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { CareEvent } from '@/api/types'
import { LogRepottingForm } from './log-repotting-form'
import { TooltipProvider } from '@/components/ui/tooltip'

vi.mock('@/hooks/useCareEventMutations', () => ({ useCareEventMutations: vi.fn() }))
import { useCareEventMutations } from '@/hooks/useCareEventMutations'

const renderWithProvider = (ui: React.ReactElement) =>
  render(<TooltipProvider>{ui}</TooltipProvider>)

const createRepotting = { mutateAsync: vi.fn() }
const updateEvent = { mutateAsync: vi.fn() }

beforeEach(() => {
  vi.clearAllMocks()
  createRepotting.mutateAsync.mockResolvedValue({ id: 11 })
  updateEvent.mutateAsync.mockResolvedValue({ id: 33 })
  vi.mocked(useCareEventMutations).mockReturnValue({
    createWatering: { mutateAsync: vi.fn() },
    createFertilizing: { mutateAsync: vi.fn() },
    createRepotting,
    createObservation: { mutateAsync: vi.fn() },
    updateEvent,
    deleteEvent: { mutateAsync: vi.fn() },
    uploadEventPhoto: { mutateAsync: vi.fn() },
  } as unknown as ReturnType<typeof useCareEventMutations>)
})

describe('LogRepottingForm', () => {
  it('chains a linked fertilizing entry at the same timestamp when fertilizer was added', async () => {
    const onDone = vi.fn()
    const onLogFertilizer = vi.fn()
    renderWithProvider(
      <LogRepottingForm plantId={4} onDone={onDone} onLogFertilizer={onLogFertilizer} />
    )

    await userEvent.click(screen.getByRole('switch'))
    await userEvent.click(screen.getByRole('button', { name: /Log repotting/ }))

    await waitFor(() => expect(onLogFertilizer).toHaveBeenCalled())
    const seeded = onLogFertilizer.mock.calls[0]?.[0]
    expect(seeded).toEqual(expect.any(String))
    // The chained timestamp is the one the repotting itself was logged at.
    expect(createRepotting.mutateAsync).toHaveBeenCalledWith(
      expect.objectContaining({ occurred_at: seeded })
    )
    expect(onDone).not.toHaveBeenCalled()
  })

  it('closes normally when no fertilizer was added', async () => {
    const onDone = vi.fn()
    const onLogFertilizer = vi.fn()
    renderWithProvider(
      <LogRepottingForm plantId={4} onDone={onDone} onLogFertilizer={onLogFertilizer} />
    )

    await userEvent.click(screen.getByRole('button', { name: /Log repotting/ }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(onLogFertilizer).not.toHaveBeenCalled()
  })

  it('routes an edit to update and does not chain a fertilizing entry', async () => {
    const onDone = vi.fn()
    const onLogFertilizer = vi.fn()
    const event: CareEvent = {
      id: 33,
      plant_id: 4,
      care_event_type_id: 3,
      type: 'repotting',
      occurred_at: '2026-06-10T11:00:00.000Z',
      logged_by_user_id: 1,
      note: 'roots circling',
      created_at: '2026-06-10T11:00:00.000Z',
      updated_at: '2026-06-10T11:00:00.000Z',
      repotting: {
        care_event_id: 33,
        soil_recipe: 'bark mix',
        pot_size_value: 10,
        pot_size_unit: 'in',
        fertilizer_added: true,
      },
    }

    renderWithProvider(
      <LogRepottingForm
        plantId={4}
        onDone={onDone}
        onLogFertilizer={onLogFertilizer}
        event={event}
      />
    )

    expect(screen.getByDisplayValue('bark mix')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Save changes/ }))

    await waitFor(() =>
      expect(updateEvent.mutateAsync).toHaveBeenCalledWith(expect.objectContaining({ eventId: 33 }))
    )
    expect(createRepotting.mutateAsync).not.toHaveBeenCalled()
    // Editing an existing repot must not spawn a new linked fertilizing entry.
    expect(onLogFertilizer).not.toHaveBeenCalled()
  })
})
