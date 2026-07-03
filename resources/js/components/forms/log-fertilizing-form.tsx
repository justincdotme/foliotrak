import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { AlertTriangle, FlaskConical, Plus, X } from 'lucide-react'
import type { CareEvent } from '@/api/types'
import { useCareEventMutations } from '@/hooks/useCareEventMutations'
import { useFertilizerForms, useNutrients } from '@/hooks/useCareLookups'
import { isoToLocal, nowLocal, toIso } from '@/lib/datetime'
import { handleApiError } from '@/lib/handle-api-error'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/app/field'
import { Input, inputClass } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { IconButton } from '@/components/app/icon-button'
import { cn } from '@/lib/utils'
import { DateTimeField } from './date-time-field'

const schema = z.object({
  occurred_at: z.string().min(1, 'Pick a date and time'),
  fertilizer_form_id: z.string().min(1, 'Pick a form'),
  brand: z.string(),
  product: z.string(),
  npk_n: z.string(),
  npk_p: z.string(),
  npk_k: z.string(),
  dose_pct: z.string(),
  amount_ml: z.string(),
  note: z.string(),
})

interface NutrientRow {
  nutrient_id: string
  note: string
}

interface LogFertilizingFormProps {
  plantId: number
  onDone: () => void
  event?: CareEvent
  seedOccurredAt?: string
}

export function LogFertilizingForm({
  plantId,
  onDone,
  event,
  seedOccurredAt,
}: LogFertilizingFormProps) {
  const { createFertilizing, updateEvent } = useCareEventMutations(plantId)
  const { data: forms } = useFertilizerForms()
  const { data: nutrientOptions } = useNutrients()

  const detail = event?.fertilizing
  const initialOccurredAt = event
    ? isoToLocal(event.occurred_at)
    : seedOccurredAt
      ? isoToLocal(seedOccurredAt)
      : nowLocal()

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
      occurred_at: initialOccurredAt,
      fertilizer_form_id: detail ? String(detail.fertilizer_form_id) : '',
      brand: detail?.brand ?? '',
      product: detail?.product ?? '',
      npk_n: detail?.npk_n != null ? String(detail.npk_n) : '',
      npk_p: detail?.npk_p != null ? String(detail.npk_p) : '',
      npk_k: detail?.npk_k != null ? String(detail.npk_k) : '',
      dose_pct: detail?.dose_pct != null ? String(detail.dose_pct) : '50',
      amount_ml: detail?.amount_ml != null ? String(detail.amount_ml) : '',
      note: event?.note ?? '',
    },
  })

  const [nutrients, setNutrients] = useState<NutrientRow[]>(
    detail?.nutrients?.map(n => ({ nutrient_id: String(n.nutrient_id), note: n.note ?? '' })) ?? []
  )

  const formValue = watch('fertilizer_form_id')

  // The form vocabulary loads from the live lookup; default a new entry to liquid
  // once it arrives so the required form id is always set.
  useEffect(() => {
    if (event || formValue || forms.length === 0) return
    const liquid = forms.find(x => x.key === 'liquid') ?? forms[0]
    if (liquid) setValue('fertilizer_form_id', String(liquid.id))
  }, [event, formValue, forms, setValue])

  const isOrganic = forms.find(x => x.id === Number(formValue))?.key === 'organic'

  const addNutrient = () => {
    const first = nutrientOptions[0]
    if (!first) return
    setNutrients(n => [...n, { nutrient_id: String(first.nutrient_id), note: '' }])
  }
  const removeNutrient = (i: number) => setNutrients(n => n.filter((_, idx) => idx !== i))
  const updateNutrient = (i: number, field: 'nutrient_id' | 'note', value: string) =>
    setNutrients(n => {
      const next = [...n]
      if (next[i]) {
        next[i][field] = value
      }
      return next
    })

  const onSubmit = async (v: z.infer<typeof schema>) => {
    setFormError(null)
    try {
      const payload = {
        occurred_at: toIso(v.occurred_at),
        fertilizer_form_id: Number(v.fertilizer_form_id),
        brand: v.brand || null,
        product: v.product || null,
        npk_n: v.npk_n ? Number(v.npk_n) : null,
        npk_p: v.npk_p ? Number(v.npk_p) : null,
        npk_k: v.npk_k ? Number(v.npk_k) : null,
        dose_pct: v.dose_pct ? Number(v.dose_pct) : null,
        amount_ml: v.amount_ml ? Number(v.amount_ml) : null,
        // Nutrient components belong to organic / multi-nutrient feeds; clear them
        // when the form is anything else, including on an edit that switches forms.
        nutrients: isOrganic
          ? nutrients.map(n => ({ nutrient_id: Number(n.nutrient_id), note: n.note || null }))
          : [],
        note: v.note || null,
      }
      if (event) await updateEvent.mutateAsync({ eventId: event.id, payload })
      else await createFertilizing.mutateAsync(payload)
      onDone()
    } catch (err) {
      const msg = handleApiError(err, setError)
      if (msg) setFormError(msg)
    }
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <DateTimeField register={register} name="occurred_at" error={errors.occurred_at?.message} />
      <Field label="Form" error={errors.fertilizer_form_id?.message}>
        <select
          {...register('fertilizer_form_id')}
          className={inputClass}
          aria-label="Form"
          dusk="fertilizing-form-select"
        >
          {forms.map(form => (
            <option key={form.id} value={String(form.id)}>
              {form.label}
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
                  aria-label="Nutrient"
                >
                  {nutrientOptions.map(nut => (
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
      {formError && (
        <div className="flex items-center gap-1.5 text-[12px] text-overdue">
          <AlertTriangle size={14} />
          {formError}
        </div>
      )}
      <div className="flex justify-end gap-2 pt-1">
        <Button type="submit" dusk="care-form-submit" disabled={isSubmitting}>
          <FlaskConical size={16} />
          {event ? 'Save changes' : 'Log fertilizing'}
        </Button>
      </div>
    </form>
  )
}
