import type { ComponentProps } from 'react'
import { cn } from '@/lib/utils'

export const inputClass =
  'w-full h-11 px-3 rounded-md bg-surface-raised border border-border-strong text-text placeholder:text-text-subtle focus:border-primary outline-none transition-colors'

export function Input({ className, ref, ...props }: ComponentProps<'input'>) {
  return <input ref={ref} className={cn(inputClass, className)} {...props} />
}
