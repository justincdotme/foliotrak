import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Modal } from './modal'

vi.mock('@/components/app/app-context', () => ({
  useAppContext: () => ({ mobile: false }),
}))

describe('Modal', () => {
  it('does not call onClose when Escape is pressed', async () => {
    const onClose = vi.fn()
    render(
      <Modal open onClose={onClose} title="Test">
        <p>Content</p>
      </Modal>
    )

    await userEvent.keyboard('{Escape}')
    expect(onClose).not.toHaveBeenCalled()
  })

  it('calls onClose when the X button is clicked', async () => {
    const onClose = vi.fn()
    render(
      <Modal open onClose={onClose} title="Test">
        <p>Content</p>
      </Modal>
    )

    await userEvent.click(screen.getByLabelText('Close'))
    expect(onClose).toHaveBeenCalledOnce()
  })
})
