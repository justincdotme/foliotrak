import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Droplets } from 'lucide-react'
import { mockApi } from '@/api/mock'
import { commit } from '@/hooks/useAsync'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/app/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { DateTimeField } from './date-time-field'

const nowLocal = (): string => {
  const d = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(8)}:${pad(0)}`
}

const toIso = (local: string): string => new Date(local).toISOString()

const schema = z.object({
  occurred_at: z.string().min(1, 'Pick a date and time'),
  amount_ml: z.string(),
  note: z.string(),
})

interface LogWateringFormProps {
  plantId: number
  onDone: () => void
}

export function LogWateringForm({ plantId, onDone }: LogWateringFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: { occurred_at: nowLocal(), amount_ml: '', note: '' },
  })

  const onSubmit = async (v: { occurred_at: string; amount_ml: string; note: string }) => {
    await mockApi.createWatering(plantId, {
      occurred_at: toIso(v.occurred_at),
      amount_ml: v.amount_ml ? Number(v.amount_ml) : null,
      note: v.note || null,
    })
    commit()
    onDone()
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
      <div className="flex justify-end gap-2 pt-1">
        <Button type="submit" disabled={isSubmitting}>
          <Droplets size={16} />
          Log watering
        </Button>
      </div>
    </form>
  )
}
