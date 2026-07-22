import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { CareEvent } from '@/api/types'
import { EventDetail } from './event-detail'

describe('EventDetail', () => {
  it('renders an added equipment change', () => {
    const e = {
      id: 1,
      plant_id: 1,
      care_event_type_id: 6,
      type: 'equipment',
      occurred_at: '2026-07-04T00:00:00Z',
      logged_by_user_id: 1,
      note: null,
      created_at: '2026-07-04T00:00:00Z',
      updated_at: '2026-07-04T00:00:00Z',
      equipment_change: {
        care_event_id: 1,
        equipment_id: 5,
        equipment_label: 'Humidifier',
        action: 'added' as const,
      },
    } as CareEvent

    render(<EventDetail e={e} />)

    expect(screen.getByText(/Added/)).toBeInTheDocument()
    expect(screen.getByText('Humidifier')).toBeInTheDocument()
  })

  it('renders a removed equipment change', () => {
    const e = {
      id: 2,
      plant_id: 1,
      care_event_type_id: 6,
      type: 'equipment',
      occurred_at: '2026-07-04T00:00:00Z',
      logged_by_user_id: 1,
      note: null,
      created_at: '2026-07-04T00:00:00Z',
      updated_at: '2026-07-04T00:00:00Z',
      equipment_change: {
        care_event_id: 2,
        equipment_id: 3,
        equipment_label: 'Grow Light',
        action: 'removed' as const,
      },
    } as CareEvent

    render(<EventDetail e={e} />)

    expect(screen.getByText(/Removed/)).toBeInTheDocument()
    expect(screen.getByText('Grow Light')).toBeInTheDocument()
  })
})
