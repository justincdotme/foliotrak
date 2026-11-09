import type { ButtonHTMLAttributes, ReactNode, Ref } from 'react'
import { cn } from '@/lib/utils'

interface IconButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  label: string
  children: ReactNode
  ref?: Ref<HTMLButtonElement>
}

export function IconButton({ label, className, children, ref, ...props }: IconButtonProps) {
  return (
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
}
