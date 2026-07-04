import { useState } from 'react'
import { Check, Plus, X } from 'lucide-react'
import type { Symptom } from '@/api/types'
import { Button } from '@/components/ui/button'
import { Chip } from '@/components/app/chip'
import { Input } from '@/components/ui/input'

const CAT_LABEL: Record<string, string> = {
  leaf: 'Leaves',
  stem: 'Stem',
  root: 'Roots',
  pest: 'Pests',
  disease: 'Disease',
  general: 'General',
}

export interface SymptomValue {
  ids: number[]
  customs: string[]
}

interface SymptomReportProps {
  allSymptoms: Symptom[]
  defaultIds?: number[]
  defaultCustoms?: string[]
  onChange: (value: SymptomValue) => void
}

export function SymptomReport({
  allSymptoms,
  defaultIds = [],
  defaultCustoms = [],
  onChange,
}: SymptomReportProps) {
  const [ids, setIds] = useState<number[]>(defaultIds)
  const [customs, setCustoms] = useState<string[]>(defaultCustoms)
  const [customDraft, setCustomDraft] = useState('')

  const toggle = (id: number) => {
    const next = ids.includes(id) ? ids.filter(x => x !== id) : [...ids, id]
    setIds(next)
    onChange({ ids: next, customs })
  }

  const addCustom = () => {
    const v = customDraft.trim()
    if (v && !customs.includes(v)) {
      const next = [...customs, v]
      setCustoms(next)
      setCustomDraft('')
      onChange({ ids, customs: next })
    }
  }

  const removeCustom = (value: string) => {
    const next = customs.filter(x => x !== value)
    setCustoms(next)
    onChange({ ids, customs: next })
  }

  // Custom entries are captured by the freetext field below, not the chips.
  const byCat: Record<string, Symptom[]> = {}
  allSymptoms
    .filter(s => !s.is_custom && s.category !== 'custom')
    .forEach(s => {
      ;(byCat[s.category] ??= []).push(s)
    })

  return (
    <>
      <p className="text-[12px] text-text-subtle">
        Flag any symptoms. These feed the Flagged problems list and the at-a-glance condition.
      </p>
      <div className="space-y-2.5">
        {Object.entries(byCat).map(([cat, list]) => (
          <div key={cat}>
            <div className="mb-1.5 text-[11px] uppercase tracking-wide text-text-subtle">
              {CAT_LABEL[cat] || cat}
            </div>
            <div className="flex flex-wrap gap-1.5">
              {list.map(s => {
                const numId = Number(s.id)
                const sel = ids.includes(numId)
                return (
                  <Chip
                    key={s.id}
                    active={sel}
                    outline={!sel}
                    color="var(--accent)"
                    onClick={() => toggle(numId)}
                    dusk={`symptom-${s.key}`}
                  >
                    {sel && <Check size={12} />}
                    {s.label}
                  </Chip>
                )
              })}
            </div>
          </div>
        ))}
      </div>
      <div>
        <div className="mb-1.5 text-[11px] uppercase tracking-wide text-text-subtle">Custom</div>
        {customs.length > 0 && (
          <div className="mb-2 flex flex-wrap gap-1.5">
            {customs.map(c => (
              <Chip key={c} active color="var(--accent)" onClick={() => removeCustom(c)}>
                {c}
                <X size={12} />
              </Chip>
            ))}
          </div>
        )}
        <div className="flex gap-2">
          <Input
            value={customDraft}
            onChange={e => setCustomDraft(e.target.value)}
            onKeyDown={e => {
              if (e.key === 'Enter') {
                e.preventDefault()
                addCustom()
              }
            }}
            placeholder="Describe another symptom"
            aria-label="Custom symptom"
          />
          <Button type="button" variant="outline" onClick={addCustom}>
            <Plus size={16} />
            Add
          </Button>
        </div>
      </div>
    </>
  )
}
