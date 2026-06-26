import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Move } from 'lucide-react'
import type { CareEvent } from '@/api/types'
import { useCareEventMutations } from '@/hooks/useCareEventMutations'
import { isoToLocal, toIso } from '@/lib/datetime'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/app/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { DateTimeField } from './date-time-field'

const schema = z.object({
  occurred_at: z.string().min(1, 'Pick a date and time'),
  to_location: z.string().min(1, 'Where did it move to?'),
  note: z.string(),
})

interface RelocationEditFormProps {
  plantId: number
  event: CareEvent
  onDone: () => void
}

// A move is created by editing the plant's location; this edits the logged move
// itself (its destination drives plants.location when it is the latest one).
export function RelocationEditForm({ plantId, event, onDone }: RelocationEditFormProps) {
  const { updateEvent } = useCareEventMutations(plantId)
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      occurred_at: isoToLocal(event.occurred_at),
      to_location: event.relocation?.to_location ?? '',
      note: event.note ?? '',
    },
  })

  const onSubmit = async (v: z.infer<typeof schema>) => {
    await updateEvent.mutateAsync({
      eventId: event.id,
      payload: {
        occurred_at: toIso(v.occurred_at),
        to_location: v.to_location,
        note: v.note || null,
      },
    })
    onDone()
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <DateTimeField register={register} name="occurred_at" error={errors.occurred_at?.message} />
      {event.relocation?.from_location && (
        <Field label="From">
          <div className="grid h-11 items-center rounded-[8px] border border-border bg-surface px-3 text-text-muted">
            {event.relocation.from_location}
          </div>
        </Field>
      )}
      <Field label="To" error={errors.to_location?.message}>
        <Input placeholder="Bright window" {...register('to_location')} />
      </Field>
      <Field label="Note" hint="optional">
        <Textarea {...register('note')} />
      </Field>
      <div className="flex justify-end gap-2 pt-1">
        <Button type="submit" disabled={isSubmitting}>
          <Move size={16} />
          Save changes
        </Button>
      </div>
    </form>
  )
}
