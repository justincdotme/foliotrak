import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import {
  AlertTriangle,
  Check,
  ClipboardList,
  HeartPulse,
  ImageIcon,
  Moon,
  Plus,
  Sun,
  X,
} from 'lucide-react'
import { mockApi, SYMPTOMS } from '@/api/mock'
import { commit } from '@/hooks/useAsync'
import { HEALTH_LABELS, HEALTH_VAR } from '@/lib/domain'
import { weightToGrams } from '@/api/types'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/app/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Chip } from '@/components/app/chip'
import { Segmented } from '@/components/app/segmented'
import { DateTimeField } from './date-time-field'
import { FormSection } from './form-section'

const nowLocal = (): string => {
  const d = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(8)}:${pad(0)}`
}

const toIso = (local: string): string => new Date(local).toISOString()

const schema = z.object({
  occurred_at: z.string().min(1, 'Pick a date and time'),
  overall_health: z.string(),
  health_note: z.string(),
  light_level: z.string(),
  growth_rate: z.string(),
  growth_note: z.string(),
  leaf_size_mm: z.string(),
  lb: z.string(),
  oz: z.string(),
  g: z.string(),
  note: z.string(),
})

interface LogObservationFormProps {
  plantId: number
  onDone: () => void
}

export function LogObservationForm({ plantId, onDone }: LogObservationFormProps) {
  const {
    register,
    handleSubmit,
    setValue,
    watch,
    formState: { isSubmitting },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      occurred_at: nowLocal(),
      overall_health: '',
      health_note: '',
      light_level: '5',
      growth_rate: '',
      growth_note: '',
      leaf_size_mm: '',
      lb: '0',
      oz: '0',
      g: '0',
      note: '',
    },
  })

  const [symptoms, setSymptoms] = useState<number[]>([])
  const [customs, setCustoms] = useState<string[]>([])
  const [customDraft, setCustomDraft] = useState('')

  const healthStr = watch('overall_health')
  const lightStr = watch('light_level')
  const light = Number(lightStr) || 5
  const growth = watch('growth_rate')
  const lbStr = watch('lb')
  const ozStr = watch('oz')
  const gStr = watch('g')
  const lb = Number(lbStr) || 0
  const oz = Number(ozStr) || 0
  const g = Number(gStr) || 0
  const health = healthStr ? Number(healthStr) : null

  const grams = weightToGrams({
    lb,
    oz,
    g,
  })

  const toggleSym = (id: number | string) => {
    const numId = Number(id)
    setSymptoms(s => (s.includes(numId) ? s.filter(x => x !== numId) : [...s, numId]))
  }

  const addCustom = () => {
    const v = customDraft.trim()
    if (v && !customs.includes(v)) {
      setCustoms(c => [...c, v])
      setCustomDraft('')
    }
  }

  const problemCount = symptoms.length + customs.length

  const byCat: Record<string, typeof SYMPTOMS> = {}
  SYMPTOMS.forEach(s => {
    if (!byCat[s.category]) {
      byCat[s.category] = []
    }
    ;(byCat[s.category] as typeof SYMPTOMS).push(s)
  })

  const CAT_LABEL: Record<string, string> = {
    leaf: 'Leaves',
    stem: 'Stem',
    root: 'Roots',
    pest: 'Pests',
    disease: 'Disease',
    general: 'General',
  }

  const onSubmit = async (v: z.infer<typeof schema>) => {
    const growthRate = v.growth_rate as 'none' | 'slow' | 'moderate' | 'fast' | null | undefined
    await mockApi.createObservation(plantId, {
      occurred_at: toIso(v.occurred_at),
      overall_health: v.overall_health ? Number(v.overall_health) : null,
      health_note: v.health_note || null,
      light_level: Number(v.light_level),
      growth_rate: growthRate || null,
      growth_note: v.growth_note || null,
      leaf_size_mm: v.leaf_size_mm ? Number(v.leaf_size_mm) : null,
      weight_grams: grams > 0 ? grams : null,
      symptom_ids: symptoms,
      custom_symptoms: customs,
      note: v.note || null,
    })
    commit()
    onDone()
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <DateTimeField register={register} name="occurred_at" />

      <FormSection title="Vitals" icon={HeartPulse}>
        <Field label="Overall health" hint="1–5">
          <div className="flex gap-1.5">
            {[1, 2, 3, 4, 5].map(v => {
              const sel = health === v
              const c = HEALTH_VAR[v]
              return (
                <button
                  key={v}
                  type="button"
                  onClick={() => setValue('overall_health', sel ? '' : String(v))}
                  aria-pressed={sel}
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
        <Field label="Light level" hint={`${light} / 10`}>
          <div className="flex items-center gap-3">
            <Moon size={18} className="shrink-0" style={{ color: 'var(--info)' }} />
            <input
              type="range"
              min={0}
              max={10}
              step={1}
              value={light}
              onChange={e => setValue('light_level', String(e.target.value))}
              className="flex-1"
            />
            <Sun size={18} className="shrink-0" style={{ color: 'var(--due-soon)' }} />
          </div>
        </Field>
        <Field label="Growth rate">
          <Segmented
            value={growth || ''}
            onChange={v => {
              if (growth === v) {
                setValue('growth_rate', '')
              } else {
                setValue('growth_rate', v)
              }
            }}
            options={[
              { value: 'none', label: 'None' },
              { value: 'slow', label: 'Slow' },
              { value: 'moderate', label: 'Mod.' },
              { value: 'fast', label: 'Fast' },
            ]}
          />
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Leaf size" hint="mm, optional">
            <Input type="number" placeholder="120" {...register('leaf_size_mm')} />
          </Field>
          <Field label="Weight total" hint="from below">
            <div className="grid h-11 items-center rounded-[8px] border border-border bg-surface px-3 tnum text-text-muted">
              {grams} g
            </div>
          </Field>
        </div>
        <Field label="Weight" hint="lb · oz · g">
          <div className="grid grid-cols-3 gap-2">
            <div className="relative">
              <Input type="number" min="0" {...register('lb')} />
              <span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] text-text-subtle">
                lb
              </span>
            </div>
            <div className="relative">
              <Input type="number" min="0" {...register('oz')} />
              <span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] text-text-subtle">
                oz
              </span>
            </div>
            <div className="relative">
              <Input type="number" min="0" step="0.1" {...register('g')} />
              <span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] text-text-subtle">
                g
              </span>
            </div>
          </div>
        </Field>
      </FormSection>

      <FormSection
        title={problemCount > 0 ? `Report a problem · ${problemCount}` : 'Report a problem'}
        icon={AlertTriangle}
        tone="var(--accent)"
      >
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
                  const sel = symptoms.includes(numId)
                  return (
                    <Chip
                      key={s.id}
                      active={sel}
                      outline={!sel}
                      color="var(--accent)"
                      onClick={() => toggleSym(numId)}
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
                <Chip
                  key={c}
                  active
                  color="var(--accent)"
                  onClick={() => setCustoms(cs => cs.filter(x => x !== c))}
                >
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
            />
            <Button type="button" variant="outline" onClick={addCustom}>
              <Plus size={16} />
              Add
            </Button>
          </div>
        </div>
      </FormSection>

      <Field label="Health note" hint="optional">
        <Textarea placeholder="What did you notice this week?" {...register('health_note')} />
      </Field>
      <Field label="Photo" hint="optional">
        <label className="flex h-11 cursor-pointer items-center gap-2 rounded-[8px] border border-dashed border-border-strong bg-surface-raised px-3 text-text-muted hover:text-text">
          <ImageIcon size={16} />
          <span className="text-[13px]">Attach a photo</span>
          <input type="file" accept="image/*" className="hidden" />
        </label>
      </Field>
      <div className="flex justify-end gap-2 pt-1">
        <Button type="submit" disabled={isSubmitting}>
          <ClipboardList size={16} />
          Log observation
        </Button>
      </div>
    </form>
  )
}
