import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

interface ChipProps {
  children: ReactNode
  color?: string
  active?: boolean
  outline?: boolean
  onClick?: () => void
  className?: string
  dusk?: string
}

export function Chip({
  children,
  color,
  active,
  outline,
  onClick,
  className = '',
  dusk,
}: ChipProps) {
  const style = color
    ? active || !outline
      ? { background: color, color: '#fff', borderColor: color }
      : { color, borderColor: color }
    : undefined

  const classes = cn(
    'inline-flex items-center gap-1.5 h-7 rounded-full border px-2.5 text-[12px] font-medium transition-colors',
    !color &&
      (active
        ? 'border-primary bg-primary text-white'
        : 'border-border-strong bg-surface text-text-muted hover:text-text'),
    onClick ? 'cursor-pointer' : 'cursor-default',
    className
  )

  // Display-only chips render as a span so they can sit inside a clickable card
  // without nesting a button in a button.
  if (!onClick) {
    return (
      <span style={style} className={classes} dusk={dusk}>
        {children}
      </span>
    )
  }

  return (
    <button type="button" onClick={onClick} style={style} className={classes} dusk={dusk}>
      {children}
    </button>
  )
}
