import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { TooltipButton } from './tooltip-button'
import { TooltipProvider } from './tooltip'

describe('TooltipButton', () => {
  it('renders a button when not disabled', () => {
    render(
      <TooltipProvider>
        <TooltipButton tooltipContent="Required field">Submit</TooltipButton>
      </TooltipProvider>
    )
    expect(screen.getByRole('button', { name: 'Submit' })).toBeInTheDocument()
  })

  it('renders a disabled button with tooltip when disabled and tooltipContent provided', () => {
    render(
      <TooltipProvider>
        <TooltipButton disabled tooltipContent="Enter a name">
          Submit
        </TooltipButton>
      </TooltipProvider>
    )
    const button = screen.getByRole('button', { name: 'Submit' })
    expect(button).toBeDisabled()
  })

  it('renders a disabled button without tooltip when disabled but no tooltipContent', () => {
    render(
      <TooltipProvider>
        <TooltipButton disabled>Submit</TooltipButton>
      </TooltipProvider>
    )
    const button = screen.getByRole('button', { name: 'Submit' })
    expect(button).toBeDisabled()
  })

  it('does not wrap button when enabled', () => {
    render(
      <TooltipProvider>
        <TooltipButton tooltipContent="Saving...">Submit</TooltipButton>
      </TooltipProvider>
    )
    const button = screen.getByRole('button', { name: 'Submit' })
    expect(button).not.toBeDisabled()
  })

  it('shows tooltip for "saving" state', () => {
    render(
      <TooltipProvider>
        <TooltipButton disabled tooltipContent="Saving...">
          Submit
        </TooltipButton>
      </TooltipProvider>
    )
    expect(screen.getByRole('button', { name: 'Submit' })).toBeDisabled()
  })

  it('shows tooltip for "required field" state', () => {
    render(
      <TooltipProvider>
        <TooltipButton disabled tooltipContent="Enter a plant name">
          Add plant
        </TooltipButton>
      </TooltipProvider>
    )
    expect(screen.getByRole('button', { name: 'Add plant' })).toBeDisabled()
  })
})
