import { forwardRef, type TextareaHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'
import { inputClass } from './input'

export const Textarea = forwardRef<
  HTMLTextAreaElement,
  TextareaHTMLAttributes<HTMLTextAreaElement>
>(({ className, rows = 5, ...props }, ref) => (
  <textarea
    ref={ref}
    rows={rows}
    className={cn(inputClass, 'h-auto py-2.5 min-h-[120px] leading-relaxed resize-y', className)}
    {...props}
  />
))
Textarea.displayName = 'Textarea'
