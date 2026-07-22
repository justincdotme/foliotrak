import { STATUS_STYLE } from '@/lib/domain'
import { TintedPill } from './tinted-pill'

interface StatusPillProps {
  status: string
}

export function StatusPill({ status }: StatusPillProps) {
  const s = STATUS_STYLE[status] || { bg: 'var(--text-subtle)', label: status }

  return (
    <TintedPill color={s.bg}>
      <span className="w-1.5 h-1.5 rounded-full" style={{ background: s.bg }} />
      {s.label}
    </TintedPill>
  )
}
