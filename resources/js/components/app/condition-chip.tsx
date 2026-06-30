import type { Condition } from '@/api/types'
import { CONDITION_COLOR, CONDITION_ICON } from '@/lib/domain'
import { cn } from '@/lib/utils'
import { Info } from 'lucide-react'

interface ConditionChipProps {
  cond: Condition
  size?: 'sm' | 'lg'
  dusk?: string
}

export function ConditionChip({ cond, size = 'sm', dusk }: ConditionChipProps) {
  const c = CONDITION_COLOR[cond.key]
  const IconComponent = CONDITION_ICON[cond.key] || Info

  return (
    <span
      dusk={dusk}
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full font-medium',
        size === 'sm' ? 'h-6 px-2 text-[12px]' : 'h-7 px-2.5 text-[13px]'
      )}
      style={{
        background: `color-mix(in srgb, ${c} 16%, transparent)`,
        color: c,
      }}
    >
      <IconComponent size={size === 'sm' ? 12 : 14} />
      {cond.label}
    </span>
  )
}
