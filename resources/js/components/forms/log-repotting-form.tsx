import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Info, Shovel } from 'lucide-react'
import { mockApi } from '@/api/mock'
import { commit } from '@/hooks/useAsync'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/app/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Toggle } from '@/components/app/toggle'
import { Segmented } from '@/components/app/segmented'
import { DateTimeField } from './date-time-field'

const nowLocal = (): string => {
  const d = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(8)}:${pad(0)}`
}

const toIso = (local: string): string => new Date(local).toISOString()

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
}

export function LogRepottingForm({ plantId, onDone }: LogRepottingFormProps) {
  const {
    register,
    handleSubmit,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      occurred_at: nowLocal(),
      soil_recipe: '',
      pot_size_value: '',
      pot_size_unit: 'in',
      fertilizer_added: false,
      note: '',
    },
  })

  const unit = watch('pot_size_unit')
  const fertAdded = watch('fertilizer_added')

  const onSubmit = async (v: {
    occurred_at: string
    soil_recipe: string
    pot_size_value: string
    pot_size_unit: string
    fertilizer_added: boolean
    note: string
  }) => {
    await mockApi.createRepotting(plantId, {
      occurred_at: toIso(v.occurred_at),
      soil_recipe: v.soil_recipe || null,
      pot_size_value: v.pot_size_value ? Number(v.pot_size_value) : null,
      pot_size_unit: v.pot_size_unit as 'in' | 'cm',
      fertilizer_added: v.fertilizer_added,
      note: v.note || null,
    })
    commit()
    onDone()
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
              onChange={v => setValue('pot_size_unit', v)}
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
        {fertAdded && (
          <p className="mt-2 flex items-center gap-1.5 text-[12px] text-text-muted">
            <Info size={13} />
            You can log a linked fertilizing event after saving.
          </p>
        )}
      </div>
      <Field label="Note" hint="optional">
        <Textarea {...register('note')} />
      </Field>
      <div className="flex justify-end gap-2 pt-1">
        <Button type="submit" disabled={isSubmitting}>
          <Shovel size={16} />
          Log repotting
        </Button>
      </div>
    </form>
  )
}
