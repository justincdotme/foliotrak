import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { CareEvent } from '@/api/types'
import { RelocationEditForm } from './relocation-edit-form'

vi.mock('@/hooks/useCareEventMutations', () => ({ useCareEventMutations: vi.fn() }))
import { useCareEventMutations } from '@/hooks/useCareEventMutations'

const updateEvent = { mutateAsync: vi.fn() }

beforeEach(() => {
  vi.clearAllMocks()
  updateEvent.mutateAsync.mockResolvedValue({ id: 9 })
  vi.mocked(useCareEventMutations).mockReturnValue({
    createWatering: { mutateAsync: vi.fn() },
    createFertilizing: { mutateAsync: vi.fn() },
    createRepotting: { mutateAsync: vi.fn() },
    createObservation: { mutateAsync: vi.fn() },
    updateEvent,
    deleteEvent: { mutateAsync: vi.fn() },
    uploadEventPhoto: { mutateAsync: vi.fn() },
  } as unknown as ReturnType<typeof useCareEventMutations>)
})

const relocationEvent: CareEvent = {
  id: 9,
  plant_id: 1,
  care_event_type_id: 5,
  type: 'relocation',
  occurred_at: '2026-06-20T12:00:00.000Z',
  logged_by_user_id: 1,
  note: 'moved for light',
  created_at: '2026-06-20T12:00:00.000Z',
  updated_at: '2026-06-20T12:00:00.000Z',
  relocation: { care_event_id: 9, from_location: 'shelf', to_location: 'bright window' },
}

describe('RelocationEditForm', () => {
  it('prefills the destination, shows the origin read-only, and updates without from_location', async () => {
    render(<RelocationEditForm plantId={1} event={relocationEvent} onDone={vi.fn()} />)

    expect(screen.getByDisplayValue('bright window')).toBeInTheDocument()
    // The origin is shown for context but is not an editable field.
    expect(screen.getByText('shelf')).toBeInTheDocument()
    expect(screen.queryByDisplayValue('shelf')).toBeNull()

    await userEvent.click(screen.getByRole('button', { name: /Save changes/ }))

    // The exact payload match also proves from_location is not sent.
    await waitFor(() =>
      expect(updateEvent.mutateAsync).toHaveBeenCalledWith({
        eventId: 9,
        payload: {
          occurred_at: expect.any(String),
          to_location: 'bright window',
          note: 'moved for light',
        },
      })
    )
  })
})
