import type { FieldValues, UseFormRegister, Path } from 'react-hook-form'
import { Field } from '@/components/app/field'
import { Input } from '@/components/ui/input'

interface DateTimeFieldProps<T extends FieldValues> {
  register: UseFormRegister<T>
  name: Path<T>
  error?: string
}

export function DateTimeField<T extends FieldValues>({
  register,
  name,
  error,
}: DateTimeFieldProps<T>) {
  return (
    <Field label="When" required error={error}>
      <Input type="datetime-local" {...register(name)} />
    </Field>
  )
}
