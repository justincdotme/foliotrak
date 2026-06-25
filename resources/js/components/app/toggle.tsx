import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

interface ToggleProps {
  checked: boolean
  onChange: (v: boolean) => void
  label?: ReactNode
}

export function Toggle({ checked, onChange, label }: ToggleProps) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      onClick={() => onChange(!checked)}
      className="inline-flex items-center gap-2.5"
    >
      <span
        className={cn(
          'w-11 h-6 rounded-full p-0.5 transition-colors',
          checked ? 'bg-primary' : 'bg-border-strong'
        )}
      >
        <span
          className={cn(
            'block w-5 h-5 rounded-full bg-white transition-transform',
            checked && 'translate-x-5'
          )}
        />
      </span>
      {label && <span className="text-sm text-text">{label}</span>}
    </button>
  )
}
