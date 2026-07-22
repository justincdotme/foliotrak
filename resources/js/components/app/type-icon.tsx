import { CARE_META } from '@/lib/domain'
import type { CareType } from '@/api/types'

interface TypeIconProps {
  type: CareType
  size?: number
}

export function TypeIcon({ type, size = 16 }: TypeIconProps) {
  const m = CARE_META[type]
  const IconComponent = m.icon

  return (
    <span
      className="grid place-items-center rounded-sm shrink-0"
      style={{
        width: size + 16,
        height: size + 16,
        background: `color-mix(in srgb, ${m.color} 16%, transparent)`,
        color: m.color,
      }}
    >
      <IconComponent size={size} />
    </span>
  )
}
