import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { CareEvent, Photo } from '@/api/types'
import { TimelineItem } from './timeline-item'

const relocation: CareEvent = {
  id: 9,
  plant_id: 1,
  care_event_type_id: 5,
  type: 'relocation',
  occurred_at: '2026-06-20T12:00:00.000Z',
  logged_by_user_id: 1,
  note: null,
  created_at: '2026-06-20T12:00:00.000Z',
  updated_at: '2026-06-20T12:00:00.000Z',
  relocation: { care_event_id: 9, from_location: 'shelf', to_location: 'bright window' },
}

const linkedPhoto: Photo = {
  id: 3,
  plant_id: 1,
  care_event_id: 9,
  path: '/storage/photos/move.jpg',
  thumb_path: null,
  original_filename: 'move.jpg',
  taken_on: '2026-06-20',
  caption: 'after the move',
  created_at: '2026-06-20T12:00:00.000Z',
  updated_at: '2026-06-20T12:00:00.000Z',
}

describe('TimelineItem', () => {
  it('shows a relocation move with its linked photo and routes edit to the handler', async () => {
    const onEdit = vi.fn()
    render(
      <TimelineItem
        e={relocation}
        photos={[linkedPhoto]}
        onEdit={onEdit}
        onViewPhoto={vi.fn()}
        onDelete={vi.fn()}
      />
    )

    await userEvent.click(screen.getByRole('button', { name: /Moved/ }))

    expect(screen.getByText('bright window')).toBeInTheDocument()
    expect(screen.getByAltText('after the move')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Edit/ }))
    expect(onEdit).toHaveBeenCalled()
  })
})
