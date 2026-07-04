import { describe, expect, it, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { SoilMoistureField } from './soil-moisture-field'

describe('SoilMoistureField', () => {
  it.each([
    [
      'relative mode, moist selected',
      async () => {
        await userEvent.click(screen.getByRole('radio', { name: 'Moist' }))
      },
      { relative: 'moist', precise: null },
    ],
    [
      'precise mode, slid to 7',
      async () => {
        await userEvent.click(screen.getByRole('button', { name: 'Meter (1-10)' }))
        fireEvent.change(screen.getByLabelText('Soil moisture level 1 to 10'), {
          target: { value: '7' },
        })
      },
      { relative: null, precise: 7 },
    ],
  ])('%s calls onChange with the resolved value', async (_label, interact, expected) => {
    const onChange = vi.fn()
    render(<SoilMoistureField onChange={onChange} />)

    await interact()

    expect(onChange).toHaveBeenCalledWith(expected)
  })
})
