import type { ReactNode } from 'react'

interface FieldProps {
  label: ReactNode
  hint?: ReactNode
  error?: ReactNode
  required?: boolean
  children: ReactNode
}

export function Field({ label, hint, error, children, required }: FieldProps) {
  return (
    <label className="block">
      <div className="mb-1.5 flex items-baseline gap-2">
        <span className="text-[13px] font-medium text-text">
          {label}
          {required && <span className="text-accent"> *</span>}
        </span>
        {hint && <span className="text-[12px] text-text-subtle">{hint}</span>}
      </div>
      {children}
      {error && <div className="mt-1 text-[12px] text-overdue">{error}</div>}
    </label>
  )
}
