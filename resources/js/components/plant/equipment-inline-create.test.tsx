import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { EquipmentOption } from '@/api/types'
import { EquipmentInlineCreate } from './equipment-inline-create'

const fakeEquipment: EquipmentOption = {
  id: 99,
  key: 'grow_light',
  label: 'Grow Light',
  sort_order: 5,
}

const mutateAsync = vi.fn().mockResolvedValue(fakeEquipment)

vi.mock('@/hooks/useEquipment', () => ({
  useCreateEquipment: () => ({ mutateAsync, isPending: false }),
}))

beforeEach(() => {
  mutateAsync.mockClear()
})

describe('EquipmentInlineCreate', () => {
  it('calls onCreated with the new equipment after typing and pressing Enter', async () => {
    const onCreated = vi.fn()
    render(<EquipmentInlineCreate onCreated={onCreated} />)

    await userEvent.click(screen.getByRole('button', { name: /new/i }))
    await userEvent.type(screen.getByPlaceholderText(/new equipment name/i), 'Grow Light{Enter}')

    expect(mutateAsync).toHaveBeenCalledWith('Grow Light')
    expect(onCreated).toHaveBeenCalledWith(fakeEquipment)
  })

  it('does not call onCreated when the input is empty', async () => {
    const onCreated = vi.fn()
    render(<EquipmentInlineCreate onCreated={onCreated} />)

    await userEvent.click(screen.getByRole('button', { name: /new/i }))
    await userEvent.type(screen.getByPlaceholderText(/new equipment name/i), '{Enter}')

    expect(mutateAsync).not.toHaveBeenCalled()
    expect(onCreated).not.toHaveBeenCalled()
  })

  it('closes the input on Escape without creating', async () => {
    const onCreated = vi.fn()
    render(<EquipmentInlineCreate onCreated={onCreated} />)

    await userEvent.click(screen.getByRole('button', { name: /new/i }))
    await userEvent.type(screen.getByPlaceholderText(/new equipment name/i), 'Fan{Escape}')

    expect(screen.getByRole('button', { name: /new/i })).toBeInTheDocument()
    expect(mutateAsync).not.toHaveBeenCalled()
  })

  it('shows an error when creation fails', async () => {
    mutateAsync.mockRejectedValueOnce(new Error('fail'))
    const onCreated = vi.fn()
    render(<EquipmentInlineCreate onCreated={onCreated} />)

    await userEvent.click(screen.getByRole('button', { name: /new/i }))
    await userEvent.type(screen.getByPlaceholderText(/new equipment name/i), 'Bad{Enter}')

    expect(await screen.findByText(/could not add equipment/i)).toBeInTheDocument()
    expect(onCreated).not.toHaveBeenCalled()
  })
})
