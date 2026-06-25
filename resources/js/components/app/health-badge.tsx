import { HEALTH_LABELS, HEALTH_VAR } from '@/lib/domain'
import { cn } from '@/lib/utils'

interface HealthBadgeProps {
  value: number | null
  showLabel?: boolean
  size?: 'sm' | 'lg'
}

export function HealthBadge({ value, showLabel = true, size = 'sm' }: HealthBadgeProps) {
  if (value == null) {
    return <span className="text-[12px] text-text-subtle">No rating</span>
  }

  const c = HEALTH_VAR[value]

  if (c === undefined) {
    return <span className="text-[12px] text-text-subtle">Invalid rating</span>
  }

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full font-medium',
        size === 'sm' ? 'h-6 px-2 text-[12px]' : 'h-7 px-2.5 text-[13px]'
      )}
      style={{
        background: `color-mix(in srgb, ${c} 18%, transparent)`,
        color: c,
      }}
    >
      <span className="w-2 h-2 rounded-full" style={{ background: c }} />
      {value}/5{showLabel && ` · ${HEALTH_LABELS[value]}`}
    </span>
  )
}
