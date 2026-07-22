import type { LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'

interface EmptyStateProps {
  icon?: LucideIcon
  title: ReactNode
  children?: ReactNode
}

export function EmptyState({ icon: Icon, title, children }: EmptyStateProps) {
  return (
    <div className="px-6 py-10 text-center">
      <div className="mx-auto mb-3 h-12 w-12 rounded-full bg-surface-raised grid place-items-center text-text-subtle">
        {Icon && <Icon size={22} />}
      </div>
      <div className="mb-1 font-medium text-text">{title}</div>
      <div className="mx-auto max-w-xs text-[13px] text-text-muted">{children}</div>
    </div>
  )
}
