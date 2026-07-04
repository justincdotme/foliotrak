import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { Symptom } from '@/api/types'
import { SymptomReport } from './symptom-report'

const allSymptoms: Symptom[] = [
  {
    id: 1,
    category: 'leaf',
    key: 'yellow_leaf',
    label: 'Yellowing leaves',
    sort_order: 1,
    is_custom: false,
  },
]

describe('SymptomReport', () => {
  it('toggles a seeded symptom chip on and off', async () => {
    const onChange = vi.fn()
    render(<SymptomReport allSymptoms={allSymptoms} onChange={onChange} />)

    const chip = screen.getByRole('button', { name: 'Yellowing leaves' })
    await userEvent.click(chip)
    expect(onChange).toHaveBeenLastCalledWith({ ids: [1], customs: [] })

    await userEvent.click(chip)
    expect(onChange).toHaveBeenLastCalledWith({ ids: [], customs: [] })
  })

  it('adds a custom symptom via Enter, then removes it via the X chip', async () => {
    const onChange = vi.fn()
    render(<SymptomReport allSymptoms={allSymptoms} onChange={onChange} />)

    await userEvent.type(screen.getByLabelText('Custom symptom'), 'curled tips{Enter}')
    expect(onChange).toHaveBeenLastCalledWith({ ids: [], customs: ['curled tips'] })

    await userEvent.click(screen.getByRole('button', { name: /curled tips/ }))
    expect(onChange).toHaveBeenLastCalledWith({ ids: [], customs: [] })
  })
})
