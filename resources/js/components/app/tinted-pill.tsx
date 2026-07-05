import { cn } from '@/lib/utils'

interface TintedPillProps {
  color: string
  size?: 'sm' | 'lg'
  children: React.ReactNode
  className?: string
  dusk?: string
}

export function TintedPill({ color, size = 'sm', children, className, dusk }: TintedPillProps) {
  return (
    <span
      dusk={dusk}
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full font-medium',
        size === 'sm' ? 'h-6 px-2 text-[12px]' : 'h-7 px-2.5 text-[13px]',
        className
      )}
      style={{
        background: `color-mix(in srgb, ${color} 16%, transparent)`,
        color,
      }}
    >
      {children}
    </span>
  )
}
