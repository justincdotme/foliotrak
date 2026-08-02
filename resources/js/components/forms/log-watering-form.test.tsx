import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { CareEvent } from '@/api/types'
import { LogWateringForm } from './log-watering-form'
import { TooltipProvider } from '@/components/ui/tooltip'

vi.mock('@/hooks/useCareEventMutations', () => ({ useCareEventMutations: vi.fn() }))
vi.mock('@/api/client', () => ({ fetchSensorSnapshot: vi.fn().mockResolvedValue(null) }))
import { useCareEventMutations } from '@/hooks/useCareEventMutations'

const renderWithProvider = (ui: React.ReactElement) =>
  render(<TooltipProvider>{ui}</TooltipProvider>)

const createWatering = { mutateAsync: vi.fn() }
const createObservation = { mutateAsync: vi.fn() }
const updateEvent = { mutateAsync: vi.fn() }

beforeEach(() => {
  vi.clearAllMocks()
  createWatering.mutateAsync.mockResolvedValue({ id: 1 })
  createObservation.mutateAsync.mockResolvedValue({ id: 2 })
  updateEvent.mutateAsync.mockResolvedValue({ id: 1 })
  vi.mocked(useCareEventMutations).mockReturnValue({
    createWatering,
    createFertilizing: { mutateAsync: vi.fn() },
    createRepotting: { mutateAsync: vi.fn() },
    createObservation,
    updateEvent,
    deleteEvent: { mutateAsync: vi.fn() },
    uploadEventPhoto: { mutateAsync: vi.fn() },
  } as unknown as ReturnType<typeof useCareEventMutations>)
})

afterEach(() => {
  vi.useRealTimers()
})

const wateringEvent: CareEvent = {
  id: 42,
  plant_id: 1,
  care_event_type_id: 1,
  type: 'watering',
  occurred_at: '2026-06-20T08:30:00.000Z',
  logged_by_user_id: 1,
  note: 'half can',
  created_at: '2026-06-20T08:31:00.000Z',
  updated_at: '2026-06-20T08:31:00.000Z',
  watering: { care_event_id: 42, amount_ml: 200 },
}

describe('LogWateringForm', () => {
  it('creates a watering with the entered amount', async () => {
    const onDone = vi.fn()
    renderWithProvider(<LogWateringForm plantId={1} onDone={onDone} />)

    await userEvent.type(screen.getByPlaceholderText('200'), '180')
    await userEvent.click(screen.getByRole('button', { name: /Log watering/ }))

    await waitFor(() =>
      expect(createWatering.mutateAsync).toHaveBeenCalledWith(
        expect.objectContaining({ amount_ml: 180 })
      )
    )
    expect(updateEvent.mutateAsync).not.toHaveBeenCalled()
    expect(onDone).toHaveBeenCalled()
  })

  it('edits an existing watering through update, prefilled from the event', async () => {
    renderWithProvider(<LogWateringForm plantId={1} onDone={vi.fn()} event={wateringEvent} />)

    expect(screen.getByPlaceholderText('200')).toHaveValue(200)

    await userEvent.click(screen.getByRole('button', { name: /Save changes/ }))

    await waitFor(() =>
      expect(updateEvent.mutateAsync).toHaveBeenCalledWith(
        expect.objectContaining({
          eventId: 42,
          payload: expect.objectContaining({ amount_ml: 200, note: 'half can' }),
        })
      )
    )
    expect(createWatering.mutateAsync).not.toHaveBeenCalled()
  })

  it('defaults the "When" field to the current time for a new watering', () => {
    vi.useFakeTimers({ now: new Date(2026, 6, 2, 14, 37, 0) })
    renderWithProvider(<LogWateringForm plantId={1} onDone={vi.fn()} />)

    const when = screen.getByLabelText(/when/i) as HTMLInputElement
    expect(when.value).toBe('2026-07-02T14:37')
  })

  it('creates a companion observation when moisture is selected', async () => {
    const onDone = vi.fn()
    renderWithProvider(<LogWateringForm plantId={1} onDone={onDone} />)

    await userEvent.type(screen.getByPlaceholderText('200'), '250')
    await userEvent.click(screen.getByRole('radio', { name: 'Moist' }))
    await userEvent.click(screen.getByRole('button', { name: /Log watering/ }))

    await waitFor(() =>
      expect(createWatering.mutateAsync).toHaveBeenCalledWith(
        expect.objectContaining({ amount_ml: 250 })
      )
    )
    await waitFor(() =>
      expect(createObservation.mutateAsync).toHaveBeenCalledWith(
        expect.objectContaining({
          soil_moisture_relative: 'moist',
          soil_moisture_precise: null,
        })
      )
    )
    expect(onDone).toHaveBeenCalled()
  })

  it('does not create an observation when moisture is untouched', async () => {
    const onDone = vi.fn()
    renderWithProvider(<LogWateringForm plantId={1} onDone={onDone} />)

    await userEvent.type(screen.getByPlaceholderText('200'), '150')
    await userEvent.click(screen.getByRole('button', { name: /Log watering/ }))

    await waitFor(() => expect(createWatering.mutateAsync).toHaveBeenCalled())
    expect(createObservation.mutateAsync).not.toHaveBeenCalled()
    expect(onDone).toHaveBeenCalled()
  })
})
