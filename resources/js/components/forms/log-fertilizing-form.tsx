import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { FlaskConical, Plus, X } from 'lucide-react'
import { mockApi, NUTRIENTS } from '@/api/mock'
import { commit } from '@/hooks/useAsync'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/app/field'
import { Input, inputClass } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { IconButton } from '@/components/app/icon-button'
import { cn } from '@/lib/utils'
import { DateTimeField } from './date-time-field'

const FORM_IDS = { liquid: 1, powdered: 2, granular: 3, organic: 4, food: 5, other: 6 }

const nowLocal = (): string => {
  const d = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(8)}:${pad(0)}`
}

const toIso = (local: string): string => new Date(local).toISOString()

const schema = z.object({
  occurred_at: z.string().min(1, 'Pick a date and time'),
  fertilizer_form_id: z.string(),
  brand: z.string(),
  product: z.string(),
  npk_n: z.string(),
  npk_p: z.string(),
  npk_k: z.string(),
  dose_pct: z.string(),
  amount_ml: z.string(),
  note: z.string(),
})

interface Nutrient {
  nutrient_id: string
  note: string
}

interface LogFertilizingFormProps {
  plantId: number
  onDone: () => void
}

export function LogFertilizingForm({ plantId, onDone }: LogFertilizingFormProps) {
  const [nutrients, setNutrients] = useState<Nutrient[]>([])

  const {
    register,
    handleSubmit,
    watch,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      occurred_at: nowLocal(),
      fertilizer_form_id: '1',
      dose_pct: '50',
      brand: '',
      product: '',
      npk_n: '',
      npk_p: '',
      npk_k: '',
      amount_ml: '',
      note: '',
    },
  })

  const formValue = watch('fertilizer_form_id')
  const isOrganic = Number(formValue) === FORM_IDS.organic

  const addNutrient = () => {
    setNutrients(n => [...n, { nutrient_id: '1', note: '' }])
  }

  const removeNutrient = (i: number) => {
    setNutrients(n => n.filter((_, idx) => idx !== i))
  }

  const updateNutrient = (i: number, field: 'nutrient_id' | 'note', value: string) => {
    setNutrients(n => {
      const newN = [...n]
      if (newN[i]) {
        newN[i][field] = value
      }
      return newN
    })
  }

  const onSubmit = async (v: z.infer<typeof schema>) => {
    await mockApi.createFertilizing(plantId, {
      occurred_at: toIso(v.occurred_at),
      fertilizer_form_id: Number(v.fertilizer_form_id),
      brand: v.brand || null,
      product: v.product || null,
      npk_n: v.npk_n ? Number(v.npk_n) : null,
      npk_p: v.npk_p ? Number(v.npk_p) : null,
      npk_k: v.npk_k ? Number(v.npk_k) : null,
      dose_pct: v.dose_pct ? Number(v.dose_pct) : null,
      amount_ml: v.amount_ml ? Number(v.amount_ml) : null,
      nutrients: nutrients.map(n => ({
        nutrient_id: Number(n.nutrient_id),
        note: n.note || null,
      })),
      note: v.note || null,
    })
    commit()
    onDone()
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <DateTimeField register={register} name="occurred_at" error={errors.occurred_at?.message} />
      <Field label="Form">
        <select {...register('fertilizer_form_id')} className={inputClass}>
          {Object.entries(FORM_IDS).map(([k, v]) => (
            <option key={v} value={String(v)} className="capitalize">
              {k.charAt(0).toUpperCase() + k.slice(1)}
            </option>
          ))}
        </select>
      </Field>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Brand" hint="optional">
          <Input placeholder="Dyna-Gro" {...register('brand')} />
        </Field>
        <Field label="Product" hint="optional">
          <Input placeholder="Foliage-Pro" {...register('product')} />
        </Field>
      </div>
      <Field label="NPK" hint="N · P · K">
        <div className="grid grid-cols-3 gap-2">
          <Input
            type="number"
            step="0.1"
            placeholder="N"
            aria-label="Nitrogen"
            {...register('npk_n')}
          />
          <Input
            type="number"
            step="0.1"
            placeholder="P"
            aria-label="Phosphorus"
            {...register('npk_p')}
          />
          <Input
            type="number"
            step="0.1"
            placeholder="K"
            aria-label="Potassium"
            {...register('npk_k')}
          />
        </div>
      </Field>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Dose" hint="% of label strength">
          <Input type="number" placeholder="50" {...register('dose_pct')} />
        </Field>
        <Field label="Amount" hint="ml, optional">
          <Input type="number" placeholder="240" {...register('amount_ml')} />
        </Field>
      </div>
      {isOrganic && (
        <div className="rounded-[8px] border border-border bg-surface-raised p-3">
          <div className="mb-2 flex items-center">
            <span className="text-[13px] font-medium">Nutrient components</span>
            <Button
              type="button"
              size="sm"
              variant="ghost"
              className="ml-auto"
              onClick={addNutrient}
            >
              <Plus size={14} />
              Add
            </Button>
          </div>
          {nutrients.length === 0 && (
            <p className="text-[12px] text-text-subtle">
              For organic / multi-nutrient feeds, list the components.
            </p>
          )}
          <div className="space-y-2">
            {nutrients.map((n, i) => (
              <div key={i} className="flex gap-2">
                <select
                  value={n.nutrient_id}
                  onChange={e => updateNutrient(i, 'nutrient_id', e.target.value)}
                  className={cn(inputClass, 'flex-1')}
                >
                  {NUTRIENTS.map(nut => (
                    <option key={nut.nutrient_id} value={String(nut.nutrient_id)}>
                      {nut.nutrient_label}
                    </option>
                  ))}
                </select>
                <Input
                  placeholder="note"
                  className="flex-1"
                  value={n.note}
                  onChange={e => updateNutrient(i, 'note', e.target.value)}
                />
                <IconButton label="Remove" onClick={() => removeNutrient(i)} className="shrink-0">
                  <X size={16} />
                </IconButton>
              </div>
            ))}
          </div>
        </div>
      )}
      <Field label="Note" hint="optional">
        <Textarea {...register('note')} />
      </Field>
      <div className="flex justify-end gap-2 pt-1">
        <Button type="submit" disabled={isSubmitting}>
          <FlaskConical size={16} />
          Log fertilizing
        </Button>
      </div>
    </form>
  )
}
