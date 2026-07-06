import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Info, Shovel } from 'lucide-react'
import type { CareEvent } from '@/api/types'
import { useCareEventMutations } from '@/hooks/useCareEventMutations'
import { useCareFormSubmit } from '@/hooks/useCareFormSubmit'
import { isoToLocal, nowLocal, toIso } from '@/lib/datetime'
import { TooltipButton } from '@/components/ui/tooltip-button'
import { Field } from '@/components/app/field'
import { FormError } from '@/components/app/form-error'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
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

  const { submit, formError } = useCareFormSubmit({
    createFn: createRepotting.mutateAsync,
    updateFn: updateEvent.mutateAsync,
    eventId: event?.id,
    setError,
  })

  const onSubmit = async (v: z.infer<typeof schema>) => {
    const occurredAt = toIso(v.occurred_at)
    const payload = {
      occurred_at: occurredAt,
      soil_recipe: v.soil_recipe || null,
      pot_size_value: v.pot_size_value ? Number(v.pot_size_value) : null,
      pot_size_unit: v.pot_size_unit,
      fertilizer_added: v.fertilizer_added,
      note: v.note || null,
    }
    await submit(payload, () => {
      if (!event && v.fertilizer_added && onLogFertilizer) {
        onLogFertilizer(occurredAt)
      } else {
        onDone()
      }
    })
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
            dusk="repotting-pot-size"
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
        <div className="inline-flex items-center gap-2.5">
          <Switch
            id="fertilizer-added"
            checked={fertAdded}
            onCheckedChange={v => setValue('fertilizer_added', v)}
          />
          <label htmlFor="fertilizer-added" className="cursor-pointer text-sm text-text">
            Fertilizer added during repot
          </label>
        </div>
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
      <FormError message={formError} />
      <div className="flex justify-end gap-2 pt-1">
        <TooltipButton
          type="submit"
          dusk="care-form-submit"
          disabled={isSubmitting}
          tooltipContent={isSubmitting ? 'Saving...' : undefined}
        >
          <Shovel size={16} />
          {event ? 'Save changes' : 'Log repotting'}
        </TooltipButton>
      </div>
    </form>
  )
}
