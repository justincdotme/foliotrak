import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react'
import { cn } from '@/lib/utils'

interface IconButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  label: string
  children: ReactNode
}

export const IconButton = forwardRef<HTMLButtonElement, IconButtonProps>(
  ({ label, className, children, ...props }, ref) => (
    <button
      ref={ref}
      aria-label={label}
      title={label}
      className={cn(
        'h-11 w-11 grid place-items-center rounded-md border border-border bg-surface text-text-muted transition-colors hover:bg-surface-raised hover:text-text',
        className
      )}
      {...props}
    >
      {children}
    </button>
  )
)
IconButton.displayName = 'IconButton'
