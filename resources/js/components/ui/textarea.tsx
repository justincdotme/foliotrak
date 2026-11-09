import type { ComponentProps } from 'react'
import { cn } from '@/lib/utils'
import { inputClass } from './input'

export function Textarea({ className, rows = 5, ref, ...props }: ComponentProps<'textarea'>) {
  return (
    <textarea
      ref={ref}
      rows={rows}
      className={cn(inputClass, 'h-auto py-2.5 min-h-[120px] leading-relaxed resize-y', className)}
      {...props}
    />
  )
}
