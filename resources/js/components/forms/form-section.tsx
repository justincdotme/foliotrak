import type { ReactNode } from 'react'
import type { LucideIcon } from 'lucide-react'

interface FormSectionProps {
  title: string
  icon?: LucideIcon
  tone?: string
  children: ReactNode
}

export function FormSection({ title, icon: Icon, tone, children }: FormSectionProps) {
  const bgColor = tone
    ? `color-mix(in srgb, ${tone} 16%, transparent)`
    : 'color-mix(in srgb, var(--primary) 16%, transparent)'
  const textColor = tone || 'var(--primary)'

  return (
    <div className="space-y-4 rounded-[10px] border border-border bg-surface-raised/40 p-3.5">
      <div className="mb-0.5 flex items-center gap-2">
        <span
          className="grid h-6 w-6 place-items-center rounded-[6px]"
          style={{ background: bgColor, color: textColor }}
        >
          {Icon && <Icon size={14} />}
        </span>
        <h3 className="text-[13px] font-semibold text-text">{title}</h3>
      </div>
      {children}
    </div>
  )
}
