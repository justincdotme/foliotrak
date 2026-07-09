import { Moon, Radar, Sun } from 'lucide-react'
import { Field } from '@/components/app/field'

interface LightSliderProps {
  value: number
  onChange: (value: number) => void
  sensorFilled?: boolean
}

export function LightSlider({ value, onChange, sensorFilled }: LightSliderProps) {
  return (
    <Field
      label="Light level"
      hint={
        sensorFilled ? (
          <span className="inline-flex items-center gap-1" title="From sensor">
            {value} / 10 <Radar size={12} />
          </span>
        ) : (
          <span dusk="light-value">{value} / 10</span>
        )
      }
    >
      <div className="flex items-center gap-3">
        <Moon size={18} className="shrink-0" style={{ color: 'var(--info)' }} />
        <input
          type="range"
          min={0}
          max={10}
          step={1}
          value={value}
          onChange={e => onChange(Number(e.target.value))}
          className="flex-1"
          aria-label="Light level"
          dusk="light-slider"
        />
        <Sun size={18} className="shrink-0" style={{ color: 'var(--due-soon)' }} />
      </div>
    </Field>
  )
}
