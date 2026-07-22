import type { LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'

interface SectionTitleProps {
  icon?: LucideIcon
  children: ReactNode
  action?: ReactNode
}

export function SectionTitle({ icon: Icon, children, action }: SectionTitleProps) {
  return (
    <div className="mb-3 flex items-center gap-2">
      {Icon && <Icon size={16} className="text-text-subtle" />}
      <h2 className="text-[13px] font-semibold uppercase tracking-wide text-text-muted">
        {children}
      </h2>
      <div className="ml-auto">{action}</div>
    </div>
  )
}
