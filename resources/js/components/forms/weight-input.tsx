import { useState } from 'react'
import { weightToGrams } from '@/api/types'
import { Field } from '@/components/app/field'
import { Input } from '@/components/ui/input'

export interface WeightValue {
  lb: number
  oz: number
  g: number
}

interface WeightInputProps {
  defaultValue?: WeightValue
  onChange: (value: WeightValue) => void
}

export function WeightInput({ defaultValue, onChange }: WeightInputProps) {
  const [lbStr, setLbStr] = useState(defaultValue?.lb ? String(defaultValue.lb) : '')
  const [ozStr, setOzStr] = useState(defaultValue?.oz ? String(defaultValue.oz) : '')
  const [gStr, setGStr] = useState(defaultValue?.g ? String(defaultValue.g) : '')

  const lb = Number(lbStr) || 0
  const oz = Number(ozStr) || 0
  const g = Number(gStr) || 0
  const grams = weightToGrams({ lb, oz, g })

  const setLbAndEmit = (next: string) => {
    setLbStr(next)
    const numLb = Number(next) || 0
    onChange({ lb: numLb, oz, g })
  }
  const setOzAndEmit = (next: string) => {
    setOzStr(next)
    const numOz = Number(next) || 0
    onChange({ lb, oz: numOz, g })
  }
  const setGAndEmit = (next: string) => {
    setGStr(next)
    const numG = Number(next) || 0
    onChange({ lb, oz, g: numG })
  }

  return (
    <>
      <Field label="Weight total" hint="from below">
        <div className="grid h-11 items-center rounded-[8px] border border-border bg-surface px-3 tnum text-text-muted">
          {grams} g
        </div>
      </Field>
      <Field label="Weight" hint="lb · oz · g">
        <div className="grid grid-cols-3 gap-2">
          <div className="relative">
            <Input
              type="number"
              min="0"
              aria-label="Pounds"
              dusk="weight-lb"
              value={lbStr}
              onChange={e => setLbAndEmit(e.target.value)}
            />
            <span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] text-text-subtle">
              lb
            </span>
          </div>
          <div className="relative">
            <Input
              type="number"
              min="0"
              aria-label="Ounces"
              dusk="weight-oz"
              value={ozStr}
              onChange={e => setOzAndEmit(e.target.value)}
            />
            <span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] text-text-subtle">
              oz
            </span>
          </div>
          <div className="relative">
            <Input
              type="number"
              min="0"
              step="0.1"
              aria-label="Grams"
              dusk="weight-g"
              value={gStr}
              onChange={e => setGAndEmit(e.target.value)}
            />
            <span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] text-text-subtle">
              g
            </span>
          </div>
        </div>
      </Field>
    </>
  )
}
