import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import {
  AlertTriangle,
  Check,
  ClipboardList,
  Droplets,
  HeartPulse,
  ImageIcon,
  Moon,
  Plus,
  Sun,
  Thermometer,
  X,
} from 'lucide-react'
import type { CareEvent, GrowthRate, SoilMoistureLevel, Symptom } from '@/api/types'
import { weightToGrams } from '@/api/types'
import { useCareEventMutations } from '@/hooks/useCareEventMutations'
import { useSymptoms } from '@/hooks/useCareLookups'
import { useSettings } from '@/hooks/useSettings'
import { isoToLocal, nowLocal, toIso } from '@/lib/datetime'
import { handleApiError } from '@/lib/handle-api-error'
import { HEALTH_LABELS, HEALTH_VAR } from '@/lib/domain'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/app/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Chip } from '@/components/app/chip'
import { Segmented } from '@/components/app/segmented'
import { DateTimeField } from './date-time-field'
import { FormSection } from './form-section'

const schema = z.object({
  occurred_at: z.string().min(1, 'Pick a date and time'),
  overall_health: z.string(),
  health_note: z.string(),
  light_level: z.string(),
  growth_rate: z.string(),
  growth_note: z.string(),
  leaf_size_mm: z.string(),
  ambient_humidity_pct: z.string(),
  ambient_temp: z.string(),
  lb: z.string(),
  oz: z.string(),
  g: z.string(),
  note: z.string(),
})

const CAT_LABEL: Record<string, string> = {
  leaf: 'Leaves',
  stem: 'Stem',
  root: 'Roots',
  pest: 'Pests',
  disease: 'Disease',
  general: 'General',
}

interface LogObservationFormProps {
  plantId: number
  onDone: () => void
  event?: CareEvent
}

export function LogObservationForm({ plantId, onDone, event }: LogObservationFormProps) {
  const { createObservation, updateEvent, uploadEventPhoto } = useCareEventMutations(plantId)
  const { data: allSymptoms } = useSymptoms()
  const { data: settings } = useSettings()
  const tempUnit = settings?.temperature_unit ?? 'F'

  const detail = event?.observation
  const [formError, setFormError] = useState<string | null>(null)
  const {
    register,
    handleSubmit,
    setError,
    setValue,
    watch,
    formState: { isSubmitting },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      occurred_at: event ? isoToLocal(event.occurred_at) : nowLocal(),
      overall_health: detail?.overall_health != null ? String(detail.overall_health) : '',
      health_note: detail?.health_note ?? '',
      light_level: detail?.light_level != null ? String(detail.light_level) : '5',
      growth_rate: detail?.growth_rate ?? '',
      growth_note: detail?.growth_note ?? '',
      leaf_size_mm: detail?.leaf_size_mm != null ? String(detail.leaf_size_mm) : '',
      ambient_humidity_pct:
        detail?.ambient_humidity_pct != null ? String(detail.ambient_humidity_pct) : '',
      ambient_temp: detail?.ambient_temp_display != null ? String(detail.ambient_temp_display) : '',
      lb: detail?.weight?.lb != null ? String(detail.weight.lb) : '0',
      oz: detail?.weight?.oz != null ? String(detail.weight.oz) : '0',
      g: detail?.weight?.g != null ? String(detail.weight.g) : '0',
      note: event?.note ?? '',
    },
  })

  const [symptoms, setSymptoms] = useState<number[]>(
    (detail?.symptoms ?? []).filter(s => !s.is_custom).map(s => Number(s.id))
  )
  const [customs, setCustoms] = useState<string[]>(
    (detail?.symptoms ?? []).filter(s => s.is_custom).map(s => s.label)
  )
  const [customDraft, setCustomDraft] = useState('')
  const [photoFile, setPhotoFile] = useState<File | null>(null)

  const initMoistureMode =
    detail?.soil_moisture_relative != null
      ? ('relative' as const)
      : detail?.soil_moisture_precise != null
        ? ('precise' as const)
        : ('relative' as const)
  const [moistureMode, setMoistureMode] = useState<'relative' | 'precise'>(initMoistureMode)
  const [moistureRelative, setMoistureRelative] = useState<SoilMoistureLevel | null>(
    detail?.soil_moisture_relative ?? null
  )
  const [moisturePrecise, setMoisturePrecise] = useState(
    detail?.soil_moisture_precise != null ? detail.soil_moisture_precise : 5
  )

  const healthStr = watch('overall_health')
  const lightStr = watch('light_level')
  const light = Number(lightStr) || 5
  const growth = watch('growth_rate')
  const lb = Number(watch('lb')) || 0
  const oz = Number(watch('oz')) || 0
  const g = Number(watch('g')) || 0
  const health = healthStr ? Number(healthStr) : null
  const grams = weightToGrams({ lb, oz, g })

  const toggleSym = (id: number) =>
    setSymptoms(s => (s.includes(id) ? s.filter(x => x !== id) : [...s, id]))

  const addCustom = () => {
    const v = customDraft.trim()
    if (v && !customs.includes(v)) {
      setCustoms(c => [...c, v])
      setCustomDraft('')
    }
  }

  const problemCount = symptoms.length + customs.length

  // Custom entries are captured by the freetext field below, not the chips.
  const byCat: Record<string, Symptom[]> = {}
  allSymptoms
    .filter(s => !s.is_custom && s.category !== 'custom')
    .forEach(s => {
      ;(byCat[s.category] ??= []).push(s)
    })

  const onSubmit = async (v: z.infer<typeof schema>) => {
    setFormError(null)
    try {
      const payload = {
        occurred_at: toIso(v.occurred_at),
        overall_health: v.overall_health ? Number(v.overall_health) : null,
        health_note: v.health_note || null,
        light_level: Number(v.light_level),
        growth_rate: (v.growth_rate || null) as GrowthRate | null,
        growth_note: v.growth_note || null,
        leaf_size_mm: v.leaf_size_mm ? Number(v.leaf_size_mm) : null,
        weight: grams > 0 ? { lb, oz, g } : null,
        ambient_humidity_pct: v.ambient_humidity_pct ? Number(v.ambient_humidity_pct) : null,
        ambient_temp: v.ambient_temp !== '' ? Number(v.ambient_temp) : null,
        soil_moisture_relative: moistureMode === 'relative' ? moistureRelative : null,
        soil_moisture_precise: moistureMode === 'precise' ? moisturePrecise : null,
        symptom_ids: symptoms,
        custom_symptoms: customs,
        note: v.note || null,
      }

      const saved = event
        ? await updateEvent.mutateAsync({ eventId: event.id, payload })
        : await createObservation.mutateAsync(payload)

      if (photoFile) {
        try {
          await uploadEventPhoto.mutateAsync({ file: photoFile, careEventId: saved.id })
        } catch {
          // The observation is saved; a failed photo can be re-added from the gallery.
        }
      }
      onDone()
    } catch (err) {
      const msg = handleApiError(err, setError)
      if (msg) setFormError(msg)
    }
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
        <hr className="border-border" />
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
              aria-label="Light level"
            />
            <Sun size={18} className="shrink-0" style={{ color: 'var(--due-soon)' }} />
          </div>
        </Field>
        <hr className="border-border" />
        <Field label="Growth rate">
          <Segmented
            value={growth || ''}
            onChange={v => setValue('growth_rate', growth === v ? '' : v)}
            options={[
              { value: 'none', label: 'None' },
              { value: 'slow', label: 'Slow' },
              { value: 'moderate', label: 'Mod.' },
              { value: 'fast', label: 'Fast' },
            ]}
          />
        </Field>
        <hr className="border-border" />
        <div className="grid grid-cols-2 gap-3">
          <Field label="Humidity" hint="%, ambient">
            <div className="relative">
              <Droplets
                size={16}
                className="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2"
                style={{ color: 'var(--info)' }}
              />
              <Input
                type="number"
                min="0"
                max="100"
                placeholder="55"
                className="pl-8"
                aria-label="Ambient humidity percent"
                {...register('ambient_humidity_pct')}
              />
              <span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] text-text-subtle">
                %
              </span>
            </div>
          </Field>
          <Field label="Temperature" hint={`°${tempUnit}, ambient`}>
            <div className="relative">
              <Thermometer
                size={16}
                className="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2"
                style={{ color: 'var(--accent)' }}
              />
              <Input
                type="number"
                step="0.1"
                placeholder={tempUnit === 'F' ? '72' : '22'}
                className="pl-8"
                aria-label={`Ambient temperature in degrees ${tempUnit === 'F' ? 'Fahrenheit' : 'Celsius'}`}
                {...register('ambient_temp')}
              />
              <span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] text-text-subtle">
                {`°${tempUnit}`}
              </span>
            </div>
          </Field>
        </div>
        <hr className="border-border" />
        <Field label="Soil moisture">
          <div className="space-y-2">
            <div className="flex gap-1.5">
              <button
                type="button"
                className={`flex-1 rounded-[8px] border px-2 py-1.5 text-[12px] font-medium transition-colors ${
                  moistureMode === 'relative'
                    ? 'border-primary bg-primary/10 text-primary'
                    : 'border-border-strong text-text-muted'
                }`}
                onClick={() => setMoistureMode('relative')}
              >
                Quick check
              </button>
              <button
                type="button"
                className={`flex-1 rounded-[8px] border px-2 py-1.5 text-[12px] font-medium transition-colors ${
                  moistureMode === 'precise'
                    ? 'border-primary bg-primary/10 text-primary'
                    : 'border-border-strong text-text-muted'
                }`}
                onClick={() => setMoistureMode('precise')}
              >
                Meter (1-10)
              </button>
            </div>
            {moistureMode === 'relative' ? (
              <Segmented
                value={moistureRelative ?? ''}
                onChange={v =>
                  setMoistureRelative(moistureRelative === v ? null : (v as SoilMoistureLevel))
                }
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
                  value={moisturePrecise}
                  onChange={e => setMoisturePrecise(Number(e.target.value))}
                  className="flex-1"
                  aria-label="Soil moisture level 1 to 10"
                />
                <span className="text-[12px] text-text-subtle">10</span>
                <span className="tnum text-[13px] text-text min-w-[2ch] text-right">
                  {moisturePrecise}
                </span>
              </div>
            )}
          </div>
        </Field>
        <hr className="border-border" />
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
              <Input type="number" min="0" aria-label="Pounds" {...register('lb')} />
              <span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] text-text-subtle">
                lb
              </span>
            </div>
            <div className="relative">
              <Input type="number" min="0" aria-label="Ounces" {...register('oz')} />
              <span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] text-text-subtle">
                oz
              </span>
            </div>
            <div className="relative">
              <Input type="number" min="0" step="0.1" aria-label="Grams" {...register('g')} />
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
              aria-label="Custom symptom"
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
          <span className="text-[13px]">{photoFile ? photoFile.name : 'Attach a photo'}</span>
          <input
            type="file"
            accept="image/*"
            className="hidden"
            onChange={e => setPhotoFile(e.target.files?.[0] ?? null)}
          />
        </label>
      </Field>
      {formError && (
        <div className="flex items-center gap-1.5 text-[12px] text-overdue">
          <AlertTriangle size={14} />
          {formError}
        </div>
      )}
      <div className="flex justify-end gap-2 pt-1">
        <Button type="submit" dusk="care-form-submit" disabled={isSubmitting}>
          <ClipboardList size={16} />
          {event ? 'Save changes' : 'Log observation'}
        </Button>
      </div>
    </form>
  )
}
