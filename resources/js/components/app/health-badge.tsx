import { HEALTH_LABELS, HEALTH_VAR } from '@/lib/domain'
import { TintedPill } from './tinted-pill'

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
    <TintedPill color={c} size={size}>
      <span className="w-2 h-2 rounded-full" style={{ background: c }} />
      {value}/5{showLabel && ` · ${HEALTH_LABELS[value]}`}
    </TintedPill>
  )
}
