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
  const [lb, setLb] = useState(defaultValue?.lb ?? 0)
  const [oz, setOz] = useState(defaultValue?.oz ?? 0)
  const [g, setG] = useState(defaultValue?.g ?? 0)
  const grams = weightToGrams({ lb, oz, g })

  const setLbAndEmit = (next: number) => {
    setLb(next)
    onChange({ lb: next, oz, g })
  }
  const setOzAndEmit = (next: number) => {
    setOz(next)
    onChange({ lb, oz: next, g })
  }
  const setGAndEmit = (next: number) => {
    setG(next)
    onChange({ lb, oz, g: next })
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
              value={lb}
              onChange={e => setLbAndEmit(Number(e.target.value))}
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
              value={oz}
              onChange={e => setOzAndEmit(Number(e.target.value))}
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
              value={g}
              onChange={e => setGAndEmit(Number(e.target.value))}
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
