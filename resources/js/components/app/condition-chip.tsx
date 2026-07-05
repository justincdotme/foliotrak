import type { Condition } from '@/api/types'
import { CONDITION_COLOR, CONDITION_ICON } from '@/lib/domain'
import { TintedPill } from './tinted-pill'
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
    <TintedPill color={c} size={size} dusk={dusk}>
      <IconComponent size={size === 'sm' ? 12 : 14} />
      {cond.label}
    </TintedPill>
  )
}
