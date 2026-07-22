import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { CropWorkflow } from './crop-workflow'
import { TooltipProvider } from '@/components/ui/tooltip'

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

vi.mock('@/components/app/app-context', () => ({
  useAppContext: () => ({ mobile: false }),
}))

const defaults = {
  preview: 'blob:test-preview',
  onBack: vi.fn(),
  onComplete: vi.fn(),
  onClose: vi.fn(),
  busy: false,
  failed: false,
}

const renderWithProvider = (ui: React.ReactElement) =>
  render(<TooltipProvider>{ui}</TooltipProvider>)

describe('CropWorkflow', () => {
  it('starts on the hero crop step with 2:3 aspect', () => {
    renderWithProvider(<CropWorkflow {...defaults} />)
    expect(screen.getByText('Crop hero photo (2:3)')).toBeInTheDocument()
    expect(screen.getByTestId('cropper').dataset.aspect).toBe(String(2 / 3))
  })

  it('enables Next after the hero crop area is set', async () => {
    renderWithProvider(<CropWorkflow {...defaults} />)
    expect(screen.getByRole('button', { name: /next/i })).toBeDisabled()
    await userEvent.click(screen.getByTestId('cropper'))
    expect(screen.getByRole('button', { name: /next/i })).toBeEnabled()
  })

  it('transitions to the thumbnail step after Next', async () => {
    renderWithProvider(<CropWorkflow {...defaults} />)
    await userEvent.click(screen.getByTestId('cropper'))
    await userEvent.click(screen.getByRole('button', { name: /next/i }))
    expect(screen.getByText('Crop thumbnail (1:1)')).toBeInTheDocument()
    expect(screen.getByTestId('cropper').dataset.aspect).toBe(String(1))
  })

  it('calls onComplete with both crop areas on save', async () => {
    const onComplete = vi.fn()
    renderWithProvider(<CropWorkflow {...defaults} onComplete={onComplete} />)
    await userEvent.click(screen.getByTestId('cropper'))
    await userEvent.click(screen.getByRole('button', { name: /next/i }))
    await userEvent.click(screen.getByTestId('cropper'))
    await userEvent.click(screen.getByRole('button', { name: /save cover photo/i }))
    expect(onComplete).toHaveBeenCalledWith(
      { x: 10, y: 15, width: 200, height: 300 },
      { x: 10, y: 15, width: 200, height: 300 }
    )
  })

  it('calls onBack when Back is pressed on the hero step', async () => {
    const onBack = vi.fn()
    renderWithProvider(<CropWorkflow {...defaults} onBack={onBack} />)
    await userEvent.click(screen.getByRole('button', { name: /back/i }))
    expect(onBack).toHaveBeenCalled()
  })

  it('navigates back to hero when Back is pressed on the thumbnail step', async () => {
    renderWithProvider(<CropWorkflow {...defaults} />)
    await userEvent.click(screen.getByTestId('cropper'))
    await userEvent.click(screen.getByRole('button', { name: /next/i }))
    expect(screen.getByText('Crop thumbnail (1:1)')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: /back/i }))
    expect(screen.getByText('Crop hero photo (2:3)')).toBeInTheDocument()
  })

  it('disables Save and shows uploading text when busy', async () => {
    renderWithProvider(<CropWorkflow {...defaults} busy />)
    await userEvent.click(screen.getByTestId('cropper'))
    await userEvent.click(screen.getByRole('button', { name: /next/i }))
    await userEvent.click(screen.getByTestId('cropper'))
    expect(screen.getByRole('button', { name: /uploading/i })).toBeDisabled()
  })

  it('shows error message when failed on the thumbnail step', async () => {
    renderWithProvider(<CropWorkflow {...defaults} failed />)
    await userEvent.click(screen.getByTestId('cropper'))
    await userEvent.click(screen.getByRole('button', { name: /next/i }))
    expect(screen.getByText(/could not save/i)).toBeInTheDocument()
  })
})
