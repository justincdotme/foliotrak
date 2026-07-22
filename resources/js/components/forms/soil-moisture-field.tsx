import { useEffect, useState } from 'react'
import { Radar } from 'lucide-react'
import type { SoilMoistureLevel } from '@/api/types'
import { Field } from '@/components/app/field'
import { Segmented } from '@/components/app/segmented'

export interface SoilMoistureValue {
  relative: SoilMoistureLevel | null
  precise: number | null
}

interface SoilMoistureFieldProps {
  value: SoilMoistureValue
  onChange: (value: SoilMoistureValue) => void
  sensorFilled?: boolean
}

export function SoilMoistureField({ value, onChange, sensorFilled }: SoilMoistureFieldProps) {
  const [mode, setMode] = useState<'relative' | 'precise'>(
    value.precise != null ? 'precise' : 'relative'
  )
  // Each tab remembers its last value so switching modes and back does not
  // discard a choice; only the active mode's value reaches the parent.
  const [lastRelative, setLastRelative] = useState<SoilMoistureLevel | null>(value.relative)
  const [lastPrecise, setLastPrecise] = useState<number | null>(value.precise)

  // A sensor auto-fill arrives as an external precise value; follow it so the
  // meter tab activates and shows the filled reading.
  useEffect(() => {
    if (value.precise != null) {
      setLastPrecise(value.precise)
      setMode('precise')
    } else if (value.relative != null) {
      setLastRelative(value.relative)
      setMode('relative')
    }
  }, [value.relative, value.precise])

  const precise = value.precise ?? lastPrecise ?? 5

  const selectMode = (next: 'relative' | 'precise') => {
    if (next === mode) return
    setMode(next)
    onChange({
      relative: next === 'relative' ? lastRelative : null,
      precise: next === 'precise' ? (lastPrecise ?? 5) : null,
    })
  }

  return (
    <Field
      label="Soil moisture"
      hint={
        sensorFilled ? (
          <span className="inline-flex items-center gap-1" title="From sensor">
            {precise} / 10 <Radar size={12} />
          </span>
        ) : undefined
      }
    >
      <div className="space-y-2" dusk="soil-moisture-field">
        <div className="flex gap-1.5">
          <button
            type="button"
            dusk="soil-moisture-toggle"
            aria-label="Quick check"
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
            dusk="soil-moisture-toggle"
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
            value={value.relative ?? ''}
            onChange={v => {
              const next = value.relative === v ? null : (v as SoilMoistureLevel)
              setLastRelative(next)
              onChange({ relative: next, precise: null })
            }}
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
              onChange={e => {
                const next = Number(e.target.value)
                setLastPrecise(next)
                onChange({ relative: null, precise: next })
              }}
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
