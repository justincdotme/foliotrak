import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { weightToGrams } from '@/api/types'
import { WeightInput } from './weight-input'

describe('WeightInput', () => {
  it('displays the grams total for the entered lb/oz/g', async () => {
    render(<WeightInput onChange={vi.fn()} />)

    await userEvent.clear(screen.getByLabelText('Pounds'))
    await userEvent.type(screen.getByLabelText('Pounds'), '2')
    await userEvent.clear(screen.getByLabelText('Ounces'))
    await userEvent.type(screen.getByLabelText('Ounces'), '3')
    await userEvent.clear(screen.getByLabelText('Grams'))
    await userEvent.type(screen.getByLabelText('Grams'), '10')

    const expectedGrams = weightToGrams({ lb: 2, oz: 3, g: 10 })
    expect(screen.getByText(`${expectedGrams} g`)).toBeInTheDocument()
  })
})
