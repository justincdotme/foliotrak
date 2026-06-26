import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { CareEvent } from '@/api/types'
import { LogWateringForm } from './log-watering-form'

vi.mock('@/hooks/useCareEventMutations', () => ({ useCareEventMutations: vi.fn() }))
import { useCareEventMutations } from '@/hooks/useCareEventMutations'

const createWatering = { mutateAsync: vi.fn() }
const updateEvent = { mutateAsync: vi.fn() }

beforeEach(() => {
  vi.clearAllMocks()
  createWatering.mutateAsync.mockResolvedValue({ id: 1 })
  updateEvent.mutateAsync.mockResolvedValue({ id: 1 })
  vi.mocked(useCareEventMutations).mockReturnValue({
    createWatering,
    createFertilizing: { mutateAsync: vi.fn() },
    createRepotting: { mutateAsync: vi.fn() },
    createObservation: { mutateAsync: vi.fn() },
    updateEvent,
    deleteEvent: { mutateAsync: vi.fn() },
    uploadEventPhoto: { mutateAsync: vi.fn() },
  } as unknown as ReturnType<typeof useCareEventMutations>)
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
    render(<LogWateringForm plantId={1} onDone={onDone} />)

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
    render(<LogWateringForm plantId={1} onDone={vi.fn()} event={wateringEvent} />)

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
})
