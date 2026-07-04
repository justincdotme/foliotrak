import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { AlertTriangle, ClipboardList, Droplets, HeartPulse, Thermometer } from 'lucide-react'
import type { CareEvent, GrowthRate } from '@/api/types'
import { weightToGrams } from '@/api/types'
import { useCareEventMutations } from '@/hooks/useCareEventMutations'
import { useCareFormSubmit } from '@/hooks/useCareFormSubmit'
import { useSymptoms } from '@/hooks/useCareLookups'
import { useSettings } from '@/hooks/useSettings'
import { isoToLocal, nowLocal, toIso } from '@/lib/datetime'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/app/field'
import { FormError } from '@/components/app/form-error'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Segmented } from '@/components/app/segmented'
import { DateTimeField } from './date-time-field'
import { FormSection } from './form-section'
import { HealthPicker } from './health-picker'
import { LightSlider } from './light-slider'
import { SoilMoistureField, type SoilMoistureValue } from './soil-moisture-field'
import { WeightInput, type WeightValue } from './weight-input'
import { SymptomReport, type SymptomValue } from './symptom-report'
import { PhotoAttach } from './photo-attach'

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
  note: z.string(),
})

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
      note: event?.note ?? '',
    },
  })

  const [soilMoisture, setSoilMoisture] = useState<SoilMoistureValue>({
    relative: detail?.soil_moisture_relative ?? null,
    precise: detail?.soil_moisture_precise ?? null,
  })
  const [weight, setWeight] = useState<WeightValue>({
    lb: detail?.weight?.lb ?? 0,
    oz: detail?.weight?.oz ?? 0,
    g: detail?.weight?.g ?? 0,
  })
  const [symptomData, setSymptomData] = useState<SymptomValue>({
    ids: (detail?.symptoms ?? []).filter(s => !s.is_custom).map(s => Number(s.id)),
    customs: (detail?.symptoms ?? []).filter(s => s.is_custom).map(s => s.label),
  })
  const [photoFile, setPhotoFile] = useState<File | null>(null)

  const healthStr = watch('overall_health')
  const lightStr = watch('light_level')
  const light = Number(lightStr) || 5
  const growth = watch('growth_rate')
  const health = healthStr ? Number(healthStr) : null
  const grams = weightToGrams(weight)
  const problemCount = symptomData.ids.length + symptomData.customs.length

  const { submit, formError } = useCareFormSubmit({
    createFn: createObservation.mutateAsync,
    updateFn: updateEvent.mutateAsync,
    eventId: event?.id,
    setError,
  })

  const onSubmit = async (v: z.infer<typeof schema>) => {
    const payload = {
      occurred_at: toIso(v.occurred_at),
      overall_health: v.overall_health ? Number(v.overall_health) : null,
      health_note: v.health_note || null,
      light_level: Number(v.light_level),
      growth_rate: (v.growth_rate || null) as GrowthRate | null,
      growth_note: v.growth_note || null,
      leaf_size_mm: v.leaf_size_mm ? Number(v.leaf_size_mm) : null,
      weight: grams > 0 ? weight : null,
      ambient_humidity_pct: v.ambient_humidity_pct ? Number(v.ambient_humidity_pct) : null,
      ambient_temp: v.ambient_temp !== '' ? Number(v.ambient_temp) : null,
      soil_moisture_relative: soilMoisture.relative,
      soil_moisture_precise: soilMoisture.precise,
      symptom_ids: symptomData.ids,
      custom_symptoms: symptomData.customs,
      note: v.note || null,
    }

    await submit(payload, async saved => {
      if (photoFile) {
        try {
          await uploadEventPhoto.mutateAsync({ file: photoFile, careEventId: saved.id })
        } catch {
          // The observation is saved; a failed photo can be re-added from the gallery.
        }
      }
      onDone()
    })
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <DateTimeField register={register} name="occurred_at" />

      <FormSection title="Vitals" icon={HeartPulse}>
        <HealthPicker
          value={health}
          onChange={v => setValue('overall_health', v == null ? '' : String(v))}
        />
        <hr className="border-border" />
        <LightSlider value={light} onChange={v => setValue('light_level', String(v))} />
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
        <SoilMoistureField
          defaultRelative={soilMoisture.relative}
          defaultPrecise={soilMoisture.precise}
          onChange={setSoilMoisture}
        />
        <hr className="border-border" />
        <Field label="Leaf size" hint="mm, optional">
          <Input type="number" placeholder="120" {...register('leaf_size_mm')} />
        </Field>
        <WeightInput defaultValue={weight} onChange={setWeight} />
      </FormSection>

      <FormSection
        title={problemCount > 0 ? `Report a problem · ${problemCount}` : 'Report a problem'}
        icon={AlertTriangle}
        tone="var(--accent)"
      >
        <SymptomReport
          allSymptoms={allSymptoms}
          defaultIds={symptomData.ids}
          defaultCustoms={symptomData.customs}
          onChange={setSymptomData}
        />
      </FormSection>

      <Field label="Health note" hint="optional">
        <Textarea placeholder="What did you notice this week?" {...register('health_note')} />
      </Field>
      <PhotoAttach onChange={setPhotoFile} />
      <FormError message={formError} />
      <div className="flex justify-end gap-2 pt-1">
        <Button type="submit" dusk="care-form-submit" disabled={isSubmitting}>
          <ClipboardList size={16} />
          {event ? 'Save changes' : 'Log observation'}
        </Button>
      </div>
    </form>
  )
}
