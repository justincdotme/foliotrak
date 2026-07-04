import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { InlineCombobox } from './inline-combobox'

const items = [
  { id: 1, name: 'Apple' },
  { id: 2, name: 'Banana' },
  { id: 3, name: 'Cherry' },
]

describe('InlineCombobox', () => {
  it('renders without crashing', () => {
    render(
      <InlineCombobox
        items={items}
        getItemValue={i => i.name}
        onSelect={vi.fn()}
        placeholder="Pick a fruit…"
      />
    )
    expect(screen.getByPlaceholderText('Pick a fruit…')).toBeInTheDocument()
  })

  it('calls onSelect when an item is clicked', async () => {
    const onSelect = vi.fn()
    render(
      <InlineCombobox
        items={items}
        getItemValue={i => i.name}
        onSelect={onSelect}
        placeholder="Pick a fruit…"
      />
    )

    await userEvent.click(screen.getByPlaceholderText('Pick a fruit…'))
    const apple = await screen.findByRole('option', { name: 'Apple' })
    await userEvent.click(apple)

    expect(onSelect).toHaveBeenCalledWith(items[0])
  })

  it('shows create option when query does not exactly match any item', async () => {
    render(
      <InlineCombobox
        items={items}
        getItemValue={i => i.name}
        onSelect={vi.fn()}
        onCreate={vi.fn()}
        placeholder="Pick a fruit…"
      />
    )

    await userEvent.type(screen.getByPlaceholderText('Pick a fruit…'), 'Grape')
    expect(await screen.findByText(/Create "Grape"/)).toBeInTheDocument()
  })

  it('hides create option on exact match (case-insensitive)', async () => {
    render(
      <InlineCombobox
        items={items}
        getItemValue={i => i.name}
        onSelect={vi.fn()}
        onCreate={vi.fn()}
        placeholder="Pick a fruit…"
      />
    )

    await userEvent.type(screen.getByPlaceholderText('Pick a fruit…'), 'apple')
    await screen.findByRole('option', { name: 'Apple' })
    expect(screen.queryByText(/Create "apple"/)).not.toBeInTheDocument()
  })

  it('calls onCreate when the create option is clicked', async () => {
    const onCreate = vi.fn().mockResolvedValue(undefined)
    render(
      <InlineCombobox
        items={items}
        getItemValue={i => i.name}
        onSelect={vi.fn()}
        onCreate={onCreate}
        placeholder="Pick a fruit…"
      />
    )

    await userEvent.type(screen.getByPlaceholderText('Pick a fruit…'), 'Grape')
    const createOption = await screen.findByText(/Create "Grape"/)
    await userEvent.click(createOption)

    expect(onCreate).toHaveBeenCalledWith('Grape')
  })
})
