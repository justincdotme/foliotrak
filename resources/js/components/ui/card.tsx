import type { ComponentProps } from 'react'
import { cn } from '@/lib/utils'

export function Card({ className, ref, ...props }: ComponentProps<'div'>) {
  return (
    <div
      ref={ref}
      className={cn('bg-surface border border-border rounded-card', className)}
      {...props}
    />
  )
}

export function CardHeader({ className, ref, ...props }: ComponentProps<'div'>) {
  return <div ref={ref} className={cn('flex flex-col gap-1 p-4', className)} {...props} />
}

export function CardTitle({ className, ref, ...props }: ComponentProps<'div'>) {
  return <div ref={ref} className={cn('font-semibold text-text', className)} {...props} />
}

export function CardDescription({ className, ref, ...props }: ComponentProps<'div'>) {
  return <div ref={ref} className={cn('text-[13px] text-text-muted', className)} {...props} />
}

export function CardContent({ className, ref, ...props }: ComponentProps<'div'>) {
  return <div ref={ref} className={cn('p-4', className)} {...props} />
}

export function CardFooter({ className, ref, ...props }: ComponentProps<'div'>) {
  return <div ref={ref} className={cn('flex items-center p-4', className)} {...props} />
}
