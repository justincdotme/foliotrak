import { type ReactNode } from 'react'
import { Button, type ButtonProps } from './button'
import { Tooltip, TooltipContent, TooltipTrigger } from './tooltip'

interface TooltipButtonProps extends ButtonProps {
  tooltipContent?: ReactNode
}

export function TooltipButton({ tooltipContent, disabled, ...props }: TooltipButtonProps) {
  if (!disabled || !tooltipContent) {
    return <Button disabled={disabled} {...props} />
  }

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span className="inline-flex">
          <Button disabled {...props} />
        </span>
      </TooltipTrigger>
      <TooltipContent>{tooltipContent}</TooltipContent>
    </Tooltip>
  )
}
