import { useState } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { SoilMoistureField, type SoilMoistureValue } from './soil-moisture-field'

function Harness({ onChange }: { onChange: (v: SoilMoistureValue) => void }) {
  const [value, setValue] = useState<SoilMoistureValue>({ relative: null, precise: null })
  return (
    <SoilMoistureField
      value={value}
      onChange={v => {
        setValue(v)
        onChange(v)
      }}
    />
  )
}

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
    render(<Harness onChange={onChange} />)

    await interact()

    expect(onChange).toHaveBeenLastCalledWith(expected)
  })

  it('switches to the meter tab when an external precise value arrives', () => {
    const { rerender } = render(
      <SoilMoistureField value={{ relative: null, precise: null }} onChange={vi.fn()} />
    )

    rerender(
      <SoilMoistureField value={{ relative: null, precise: 7 }} onChange={vi.fn()} sensorFilled />
    )

    expect(screen.getByLabelText('Soil moisture level 1 to 10')).toHaveValue('7')
    expect(screen.getByTitle('From sensor')).toBeInTheDocument()
  })

  it.each([
    [
      'relative value survives a meter round trip',
      async () => {
        await userEvent.click(screen.getByRole('radio', { name: 'Moist' }))
        await userEvent.click(screen.getByRole('button', { name: 'Meter (1-10)' }))
        await userEvent.click(screen.getByRole('button', { name: 'Quick check' }))
      },
      { relative: 'moist', precise: null },
    ],
    [
      'precise value survives a quick check round trip',
      async () => {
        await userEvent.click(screen.getByRole('button', { name: 'Meter (1-10)' }))
        fireEvent.change(screen.getByLabelText('Soil moisture level 1 to 10'), {
          target: { value: '7' },
        })
        await userEvent.click(screen.getByRole('button', { name: 'Quick check' }))
        await userEvent.click(screen.getByRole('button', { name: 'Meter (1-10)' }))
      },
      { relative: null, precise: 7 },
    ],
  ])('%s', async (_label, interact, expected) => {
    const onChange = vi.fn()
    render(<Harness onChange={onChange} />)

    await interact()

    expect(onChange).toHaveBeenLastCalledWith(expected)
  })

  it('ignores a click on the already-active tab', async () => {
    const onChange = vi.fn()
    render(<Harness onChange={onChange} />)

    await userEvent.click(screen.getByRole('button', { name: 'Quick check' }))

    expect(onChange).not.toHaveBeenCalled()
  })
})
