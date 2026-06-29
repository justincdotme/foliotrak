import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { AlertTriangle, Info, Shovel } from 'lucide-react'
import type { CareEvent } from '@/api/types'
import { useCareEventMutations } from '@/hooks/useCareEventMutations'
import { isoToLocal, nowLocal, toIso } from '@/lib/datetime'
import { handleApiError } from '@/lib/handle-api-error'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/app/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Toggle } from '@/components/app/toggle'
import { Segmented } from '@/components/app/segmented'
import { DateTimeField } from './date-time-field'

const schema = z.object({
  occurred_at: z.string().min(1, 'Pick a date and time'),
  soil_recipe: z.string(),
  pot_size_value: z.string(),
  pot_size_unit: z.enum(['in', 'cm']),
  fertilizer_added: z.boolean(),
  note: z.string(),
})

interface LogRepottingFormProps {
  plantId: number
  onDone: () => void
  event?: CareEvent
  // Chains a linked fertilizing entry at the same timestamp when fertilizer was
  // added during the repot, so fertilizer is always tracked the same way.
  onLogFertilizer?: (occurredAtIso: string) => void
}

export function LogRepottingForm({
  plantId,
  onDone,
  event,
  onLogFertilizer,
}: LogRepottingFormProps) {
  const { createRepotting, updateEvent } = useCareEventMutations(plantId)
  const [formError, setFormError] = useState<string | null>(null)
  const {
    register,
    handleSubmit,
    setError,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      occurred_at: event ? isoToLocal(event.occurred_at) : nowLocal(),
      soil_recipe: event?.repotting?.soil_recipe ?? '',
      pot_size_value:
        event?.repotting?.pot_size_value != null ? String(event.repotting.pot_size_value) : '',
      pot_size_unit: event?.repotting?.pot_size_unit ?? 'in',
      fertilizer_added: event?.repotting?.fertilizer_added ?? false,
      note: event?.note ?? '',
    },
  })

  const unit = watch('pot_size_unit')
  const fertAdded = watch('fertilizer_added')

  const onSubmit = async (v: z.infer<typeof schema>) => {
    setFormError(null)
    try {
      const occurredAt = toIso(v.occurred_at)
      const payload = {
        occurred_at: occurredAt,
        soil_recipe: v.soil_recipe || null,
        pot_size_value: v.pot_size_value ? Number(v.pot_size_value) : null,
        pot_size_unit: v.pot_size_unit,
        fertilizer_added: v.fertilizer_added,
        note: v.note || null,
      }

      if (event) {
        await updateEvent.mutateAsync({ eventId: event.id, payload })
        onDone()
        return
      }

      await createRepotting.mutateAsync(payload)
      if (v.fertilizer_added && onLogFertilizer) {
        onLogFertilizer(occurredAt)
      } else {
        onDone()
      }
    } catch (err) {
      const msg = handleApiError(err, setError)
      if (msg) setFormError(msg)
    }
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <DateTimeField register={register} name="occurred_at" error={errors.occurred_at?.message} />
      <Field label="Soil recipe" hint="freeform">
        <Textarea
          placeholder="5 parts bark, 2 parts coco coir, 1 part perlite"
          {...register('soil_recipe')}
        />
      </Field>
      <Field label="Pot size">
        <div className="flex gap-2">
          <Input
            type="number"
            step="0.5"
            placeholder="10"
            className="flex-1"
            {...register('pot_size_value')}
          />
          <div className="w-28">
            <Segmented
              value={unit}
              onChange={v => setValue('pot_size_unit', v as 'in' | 'cm')}
              options={[
                { value: 'in', label: 'in' },
                { value: 'cm', label: 'cm' },
              ]}
            />
          </div>
        </div>
      </Field>
      <div className="rounded-[8px] border border-border bg-surface-raised p-3">
        <Toggle
          checked={fertAdded}
          onChange={v => setValue('fertilizer_added', v)}
          label="Fertilizer added during repot"
        />
        {fertAdded && !event && (
          <p className="mt-2 flex items-center gap-1.5 text-[12px] text-text-muted">
            <Info size={13} />
            Saving opens a linked fertilizing entry at the same time.
          </p>
        )}
      </div>
      <Field label="Note" hint="optional">
        <Textarea {...register('note')} />
      </Field>
      {formError && (
        <div className="flex items-center gap-1.5 text-[12px] text-overdue">
          <AlertTriangle size={14} />
          {formError}
        </div>
      )}
      <div className="flex justify-end gap-2 pt-1">
        <Button type="submit" disabled={isSubmitting}>
          <Shovel size={16} />
          {event ? 'Save changes' : 'Log repotting'}
        </Button>
      </div>
    </form>
  )
}
