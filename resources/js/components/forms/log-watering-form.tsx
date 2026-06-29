import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { AlertTriangle, Droplets } from 'lucide-react'
import type { CareEvent } from '@/api/types'
import { useCareEventMutations } from '@/hooks/useCareEventMutations'
import { isoToLocal, nowLocal, toIso } from '@/lib/datetime'
import { handleApiError } from '@/lib/handle-api-error'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/app/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { DateTimeField } from './date-time-field'

const schema = z.object({
  occurred_at: z.string().min(1, 'Pick a date and time'),
  amount_ml: z.string(),
  note: z.string(),
})

interface LogWateringFormProps {
  plantId: number
  onDone: () => void
  event?: CareEvent
}

export function LogWateringForm({ plantId, onDone, event }: LogWateringFormProps) {
  const { createWatering, updateEvent } = useCareEventMutations(plantId)
  const [formError, setFormError] = useState<string | null>(null)
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      occurred_at: event ? isoToLocal(event.occurred_at) : nowLocal(),
      amount_ml: event?.watering?.amount_ml != null ? String(event.watering.amount_ml) : '',
      note: event?.note ?? '',
    },
  })

  const onSubmit = async (v: { occurred_at: string; amount_ml: string; note: string }) => {
    setFormError(null)
    try {
      const payload = {
        occurred_at: toIso(v.occurred_at),
        amount_ml: v.amount_ml ? Number(v.amount_ml) : null,
        note: v.note || null,
      }
      if (event) await updateEvent.mutateAsync({ eventId: event.id, payload })
      else await createWatering.mutateAsync(payload)
      onDone()
    } catch (err) {
      const msg = handleApiError(err, setError)
      if (msg) setFormError(msg)
    }
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <DateTimeField register={register} name="occurred_at" error={errors.occurred_at?.message} />
      <Field label="Amount" hint="ml, optional">
        <Input type="number" inputMode="numeric" placeholder="200" {...register('amount_ml')} />
      </Field>
      <Field label="Note" hint="optional">
        <Textarea
          placeholder="Soil was bone dry; ran water through until it drained"
          {...register('note')}
        />
      </Field>
      {formError && (
        <div className="flex items-center gap-1.5 text-[12px] text-overdue">
          <AlertTriangle size={14} />
          {formError}
        </div>
      )}
      <div className="flex justify-end gap-2 pt-1">
        <Button type="submit" disabled={isSubmitting}>
          <Droplets size={16} />
          {event ? 'Save changes' : 'Log watering'}
        </Button>
      </div>
    </form>
  )
}
