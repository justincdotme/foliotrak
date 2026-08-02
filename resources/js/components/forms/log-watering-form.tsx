import { useEffect, useRef, useState } from 'react'
import type { MutableRefObject } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Droplets } from 'lucide-react'
import type { CareEvent } from '@/api/types'
import { fetchSensorSnapshot } from '@/api/client'
import { useCareEventMutations } from '@/hooks/useCareEventMutations'
import { useCareFormSubmit } from '@/hooks/useCareFormSubmit'
import { isoToLocal, nowLocal, toIso } from '@/lib/datetime'
import { TooltipButton } from '@/components/ui/tooltip-button'
import { Field } from '@/components/app/field'
import { FormError } from '@/components/app/form-error'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { DateTimeField } from './date-time-field'
import { SoilMoistureField, type SoilMoistureValue } from './soil-moisture-field'

const schema = z.object({
  occurred_at: z.string().min(1, 'Pick a date and time'),
  amount_ml: z.string(),
  note: z.string(),
})

interface LogWateringFormProps {
  plantId: number
  onDone: () => void
  event?: CareEvent
  dirtyRef?: MutableRefObject<boolean>
}

export function LogWateringForm({ plantId, onDone, event, dirtyRef }: LogWateringFormProps) {
  const { createWatering, createObservation, updateEvent } = useCareEventMutations(plantId)
  const {
    register,
    handleSubmit,
    setError,
    watch,
    formState: { errors, isSubmitting, isDirty },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      occurred_at: event ? isoToLocal(event.occurred_at) : nowLocal(),
      amount_ml: event?.watering?.amount_ml != null ? String(event.watering.amount_ml) : '',
      note: event?.note ?? '',
    },
  })

  const [soilMoisture, setSoilMoisture] = useState<SoilMoistureValue>({
    relative: null,
    precise: null,
  })
  const [extraDirty, setExtraDirty] = useState(false)
  const touchedRef = useRef<Set<string>>(new Set())
  const [sensorFilled, setSensorFilled] = useState<Set<string>>(new Set())

  const occurredAt = watch('occurred_at')

  useEffect(() => {
    if (event) return

    let ignore = false
    const timer = setTimeout(() => {
      fetchSensorSnapshot(plantId, toIso(occurredAt))
        .then(snapshot => {
          if (ignore || !snapshot) return
          if (snapshot.soil_moisture_precise != null && !touchedRef.current.has('soil_moisture')) {
            setSoilMoisture({ relative: null, precise: snapshot.soil_moisture_precise })
            setSensorFilled(new Set(['soil_moisture']))
          }
        })
        .catch(() => {})
    }, 500)

    return () => {
      ignore = true
      clearTimeout(timer)
    }
  }, [occurredAt, plantId, event])

  const { submit, formError } = useCareFormSubmit({
    createFn: createWatering.mutateAsync,
    updateFn: updateEvent.mutateAsync,
    eventId: event?.id,
    setError,
  })

  const isFormDirty = isDirty || extraDirty

  useEffect(() => {
    if (dirtyRef) dirtyRef.current = isFormDirty
  }, [isFormDirty, dirtyRef])

  const onSubmit = async (v: { occurred_at: string; amount_ml: string; note: string }) => {
    const occurredAtIso = toIso(v.occurred_at)
    const payload = {
      occurred_at: occurredAtIso,
      amount_ml: v.amount_ml ? Number(v.amount_ml) : null,
      note: v.note || null,
    }
    await submit(payload, async () => {
      if (soilMoisture.relative != null || soilMoisture.precise != null) {
        await createObservation.mutateAsync({
          occurred_at: occurredAtIso,
          soil_moisture_relative: soilMoisture.relative,
          soil_moisture_precise: soilMoisture.precise,
        })
      }
      onDone()
    })
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <DateTimeField
        register={register}
        name="occurred_at"
        error={errors.occurred_at?.message}
        dusk="watering-date"
      />
      <Field label="Amount" hint="ml, optional">
        <Input
          type="number"
          inputMode="numeric"
          placeholder="200"
          dusk="watering-amount"
          {...register('amount_ml')}
        />
      </Field>
      <Field label="Note" hint="optional">
        <Textarea
          placeholder="Soil was bone dry; ran water through until it drained"
          dusk="watering-note"
          {...register('note')}
        />
      </Field>
      <SoilMoistureField
        value={soilMoisture}
        onChange={v => {
          touchedRef.current.add('soil_moisture')
          setSensorFilled(prev => {
            const next = new Set(prev)
            next.delete('soil_moisture')
            return next
          })
          setSoilMoisture(v)
          setExtraDirty(true)
        }}
        sensorFilled={sensorFilled.has('soil_moisture')}
      />
      <FormError message={formError} dusk="form-error" />
      <div className="flex justify-end gap-2 pt-1">
        <TooltipButton
          type="submit"
          dusk="care-form-submit"
          disabled={isSubmitting}
          tooltipContent={isSubmitting ? 'Saving...' : undefined}
        >
          <Droplets size={16} />
          {event ? 'Save changes' : 'Log watering'}
        </TooltipButton>
      </div>
    </form>
  )
}
