import { STATUS_STYLE } from '@/lib/domain'

interface StatusPillProps {
  status: string
}

export function StatusPill({ status }: StatusPillProps) {
  const s = STATUS_STYLE[status] || { bg: 'var(--text-subtle)', label: status }

  return (
    <span
      className="inline-flex items-center gap-1.5 h-6 rounded-full px-2 text-[12px] font-medium"
      style={{
        background: `color-mix(in srgb, ${s.bg} 16%, transparent)`,
        color: s.bg,
      }}
    >
      <span className="w-1.5 h-1.5 rounded-full" style={{ background: s.bg }} />
      {s.label}
    </span>
  )
}
