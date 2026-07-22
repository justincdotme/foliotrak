import { Field } from '@/components/app/field'
import { HEALTH_LABELS, HEALTH_VAR } from '@/lib/domain'

interface HealthPickerProps {
  value: number | null
  onChange: (value: number | null) => void
}

export function HealthPicker({ value, onChange }: HealthPickerProps) {
  return (
    <Field label="Overall health" hint="1–5">
      <div className="flex gap-1.5" dusk="health-picker">
        {[1, 2, 3, 4, 5].map(v => {
          const sel = value === v
          const c = HEALTH_VAR[v]
          return (
            <button
              key={v}
              type="button"
              onClick={() => onChange(sel ? null : v)}
              aria-pressed={sel}
              dusk={`health-rating-${v}`}
              className="flex min-h-[44px] flex-1 flex-col items-center justify-center gap-0.5 rounded-[8px] border text-[12px] font-medium transition-colors"
              style={
                sel
                  ? { background: c, color: '#fff', borderColor: c }
                  : { borderColor: 'var(--border-strong)', color: 'var(--text-muted)' }
              }
            >
              <span className="tnum text-sm">{v}</span>
              <span className="text-[10px] leading-tight">{HEALTH_LABELS[v]}</span>
            </button>
          )
        })}
      </div>
    </Field>
  )
}
