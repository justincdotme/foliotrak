import { useState } from 'react'
import type { SoilMoistureLevel } from '@/api/types'
import { Field } from '@/components/app/field'
import { Segmented } from '@/components/app/segmented'

export interface SoilMoistureValue {
  relative: SoilMoistureLevel | null
  precise: number | null
}

interface SoilMoistureFieldProps {
  defaultRelative?: SoilMoistureLevel | null
  defaultPrecise?: number | null
  onChange: (value: SoilMoistureValue) => void
}

export function SoilMoistureField({
  defaultRelative = null,
  defaultPrecise = null,
  onChange,
}: SoilMoistureFieldProps) {
  const initialMode =
    defaultRelative != null ? 'relative' : defaultPrecise != null ? 'precise' : 'relative'
  const [mode, setMode] = useState<'relative' | 'precise'>(initialMode)
  const [relative, setRelative] = useState<SoilMoistureLevel | null>(defaultRelative)
  const [precise, setPrecise] = useState(defaultPrecise ?? 5)

  const selectMode = (next: 'relative' | 'precise') => {
    setMode(next)
    onChange({
      relative: next === 'relative' ? relative : null,
      precise: next === 'precise' ? precise : null,
    })
  }

  const selectRelative = (next: SoilMoistureLevel | null) => {
    setRelative(next)
    onChange({ relative: next, precise: null })
  }

  const selectPrecise = (next: number) => {
    setPrecise(next)
    onChange({ relative: null, precise: next })
  }

  return (
    <Field label="Soil moisture">
      <div className="space-y-2">
        <div className="flex gap-1.5">
          <button
            type="button"
            className={`flex-1 rounded-[8px] border px-2 py-1.5 text-[12px] font-medium transition-colors ${
              mode === 'relative'
                ? 'border-primary bg-primary/10 text-primary'
                : 'border-border-strong text-text-muted'
            }`}
            onClick={() => selectMode('relative')}
          >
            Quick check
          </button>
          <button
            type="button"
            className={`flex-1 rounded-[8px] border px-2 py-1.5 text-[12px] font-medium transition-colors ${
              mode === 'precise'
                ? 'border-primary bg-primary/10 text-primary'
                : 'border-border-strong text-text-muted'
            }`}
            onClick={() => selectMode('precise')}
          >
            Meter (1-10)
          </button>
        </div>
        {mode === 'relative' ? (
          <Segmented
            value={relative ?? ''}
            onChange={v => selectRelative(relative === v ? null : (v as SoilMoistureLevel))}
            options={[
              { value: 'dry', label: 'Dry' },
              { value: 'moist', label: 'Moist' },
              { value: 'wet', label: 'Wet' },
            ]}
          />
        ) : (
          <div className="flex items-center gap-3">
            <span className="text-[12px] text-text-subtle">1</span>
            <input
              type="range"
              min={1}
              max={10}
              step={1}
              value={precise}
              onChange={e => selectPrecise(Number(e.target.value))}
              className="flex-1"
              aria-label="Soil moisture level 1 to 10"
            />
            <span className="text-[12px] text-text-subtle">10</span>
            <span className="tnum text-[13px] text-text min-w-[2ch] text-right">{precise}</span>
          </div>
        )}
      </div>
    </Field>
  )
}
