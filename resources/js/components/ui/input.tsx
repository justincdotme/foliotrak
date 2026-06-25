import { forwardRef, type InputHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

export const inputClass =
  'w-full h-11 px-3 rounded-md bg-surface-raised border border-border-strong text-text placeholder:text-text-subtle focus:border-primary outline-none transition-colors'

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(
  ({ className, ...props }, ref) => (
    <input ref={ref} className={cn(inputClass, className)} {...props} />
  )
)
Input.displayName = 'Input'
