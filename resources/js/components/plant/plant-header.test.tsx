import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { PlantWithTags } from '@/api/types'
import { PlantHeader } from './plant-header'

const mockPlant: PlantWithTags = {
  id: 1,
  common_name: 'Monstera deliciosa',
  scientific_name: 'Monstera deliciosa',
  nickname: 'Big Leaf',
  gbif_key: null,
  location: { id: 1, name: 'Living Room' },
  acquired_on: '2025-01-01',
  status: 'active',
  notes: null,
  watering_interval_days_override: null,
  watering_schedule_start_date: null,
  fertilizing_interval_days_override: null,
  cover_photo_id: null,
  cover_photo: null,
  condition: { key: 'healthy', label: 'Healthy' },
  created_at: '2025-01-01T00:00:00.000Z',
  updated_at: '2025-01-01T00:00:00.000Z',
  tags: [],
  equipment: [],
  due_for_care: [],
  last_watered_at: null,
}

describe('PlantHeader', () => {
  it('renders delete button that triggers onDelete callback', async () => {
    const onEdit = vi.fn()
    const onChangeCover = vi.fn()
    const onDelete = vi.fn()

    render(
      <PlantHeader
        plant={mockPlant}
        onEdit={onEdit}
        onChangeCover={onChangeCover}
        onDelete={onDelete}
      />
    )

    const deleteButton = screen.getByRole('button', { name: /Delete plant/i })
    expect(deleteButton).toBeInTheDocument()

    await userEvent.click(deleteButton)

    expect(onDelete).toHaveBeenCalledOnce()
    expect(onEdit).not.toHaveBeenCalled()
    expect(onChangeCover).not.toHaveBeenCalled()
  })

  it('renders edit button that triggers onEdit callback', async () => {
    const onEdit = vi.fn()
    const onChangeCover = vi.fn()
    const onDelete = vi.fn()

    render(
      <PlantHeader
        plant={mockPlant}
        onEdit={onEdit}
        onChangeCover={onChangeCover}
        onDelete={onDelete}
      />
    )

    const editButton = screen.getByRole('button', { name: /Edit plant/i })
    expect(editButton).toBeInTheDocument()

    await userEvent.click(editButton)

    expect(onEdit).toHaveBeenCalledOnce()
    expect(onDelete).not.toHaveBeenCalled()
  })
})
