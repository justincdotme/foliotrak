import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ImageCropper } from './image-cropper'

vi.mock('react-easy-crop', () => ({
  default: function MockCropper(props: Record<string, unknown>) {
    const callback = props.onCropComplete as ((...args: unknown[]) => void) | undefined
    const fire = () =>
      callback?.({ x: 0, y: 0, width: 100, height: 100 }, { x: 10, y: 15, width: 200, height: 300 })
    return (
      <div
        data-testid="cropper"
        data-aspect={props.aspect as number}
        role="button"
        tabIndex={0}
        onClick={fire}
        onKeyDown={fire}
      />
    )
  },
}))

describe('ImageCropper', () => {
  it('passes the aspect ratio to the cropper', () => {
    render(<ImageCropper image="blob:test" aspect={2 / 3} onCropComplete={vi.fn()} />)
    expect(screen.getByTestId('cropper').dataset.aspect).toBe(String(2 / 3))
  })

  it('renders a zoom slider', () => {
    render(<ImageCropper image="blob:test" aspect={1} onCropComplete={vi.fn()} />)
    expect(screen.getByRole('slider', { name: /zoom/i })).toBeInTheDocument()
  })

  it('forwards pixel coordinates from the cropper', async () => {
    const onCropComplete = vi.fn()
    render(<ImageCropper image="blob:test" aspect={1} onCropComplete={onCropComplete} />)
    await userEvent.click(screen.getByTestId('cropper'))
    expect(onCropComplete).toHaveBeenCalledWith({ x: 10, y: 15, width: 200, height: 300 })
  })
})
