import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Move } from 'lucide-react'
import type { CareEvent } from '@/api/types'
import { useCareEventMutations } from '@/hooks/useCareEventMutations'
import { useCareFormSubmit } from '@/hooks/useCareFormSubmit'
import { isoToLocal, toIso } from '@/lib/datetime'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/app/field'
import { FormError } from '@/components/app/form-error'
import { LocationCombobox } from '@/components/app/location-combobox'
import { Textarea } from '@/components/ui/textarea'
import { DateTimeField } from './date-time-field'

const schema = z.object({
  occurred_at: z.string().min(1, 'Pick a date and time'),
  note: z.string(),
})

interface RelocationEditFormProps {
  plantId: number
  event: CareEvent
  onDone: () => void
}

export function RelocationEditForm({ plantId, event, onDone }: RelocationEditFormProps) {
  const { updateEvent } = useCareEventMutations(plantId)
  const [toLocationId, setToLocationId] = useState<number | null>(
    event.relocation?.to_location?.id ?? null
  )
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      occurred_at: isoToLocal(event.occurred_at),
      note: event.note ?? '',
    },
  })

  const { submit, formError } = useCareFormSubmit({
    updateFn: updateEvent.mutateAsync,
    eventId: event.id,
    setError,
  })

  const onSubmit = async (v: z.infer<typeof schema>) => {
    const payload = {
      occurred_at: toIso(v.occurred_at),
      to_location_id: toLocationId,
      note: v.note || null,
    }
    await submit(payload, () => onDone())
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <DateTimeField register={register} name="occurred_at" error={errors.occurred_at?.message} />
      {event.relocation?.from_location && (
        <Field label="From">
          <div className="grid h-11 items-center rounded-[8px] border border-border bg-surface px-3 text-text-muted">
            {event.relocation.from_location.name}
          </div>
        </Field>
      )}
      <Field label="To">
        <LocationCombobox value={toLocationId} onChange={setToLocationId} />
      </Field>
      <Field label="Note" hint="optional">
        <Textarea {...register('note')} />
      </Field>
      <FormError message={formError} />
      <div className="flex justify-end gap-2 pt-1">
        <Button type="submit" disabled={isSubmitting || toLocationId == null}>
          <Move size={16} />
          Save changes
        </Button>
      </div>
    </form>
  )
}
