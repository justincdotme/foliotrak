import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { CareEvent } from '@/api/types'
import { RelocationEditForm } from './relocation-edit-form'

vi.mock('@/hooks/useCareEventMutations', () => ({ useCareEventMutations: vi.fn() }))
vi.mock('@/hooks/useLocations', () => ({
  useLocations: vi.fn(),
  useCreateLocation: vi.fn(),
}))
import { useCareEventMutations } from '@/hooks/useCareEventMutations'
import { useLocations, useCreateLocation } from '@/hooks/useLocations'

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
  vi.mocked(useLocations).mockReturnValue({
    data: [
      { id: 1, name: 'shelf' },
      { id: 2, name: 'bright window' },
    ],
    loading: false,
    error: null,
  })
  vi.mocked(useCreateLocation).mockReturnValue({ mutateAsync: vi.fn(), isPending: false } as never)
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
  relocation: {
    care_event_id: 9,
    from_location: { id: 1, name: 'shelf' },
    to_location: { id: 2, name: 'bright window' },
  },
}

describe('RelocationEditForm', () => {
  it('shows the origin read-only and submits with location IDs', async () => {
    render(<RelocationEditForm plantId={1} event={relocationEvent} onDone={vi.fn()} />)

    expect(screen.getByText('shelf')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Save changes/ }))

    await waitFor(() =>
      expect(updateEvent.mutateAsync).toHaveBeenCalledWith({
        eventId: 9,
        payload: {
          occurred_at: expect.any(String),
          to_location_id: 2,
          note: 'moved for light',
        },
      })
    )
  })
})
