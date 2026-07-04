import { useCallback, useState } from 'react'
import type { FieldValues, UseFormSetError } from 'react-hook-form'
import { handleApiError } from '@/lib/handle-api-error'

interface UseCareFormSubmitOptions<T extends FieldValues = FieldValues> {
  createFn?: (payload: any) => Promise<any> // eslint-disable-line @typescript-eslint/no-explicit-any -- wraps mutations with different payload/return shapes
  updateFn?: (params: { eventId: number; payload: any }) => Promise<any> // eslint-disable-line @typescript-eslint/no-explicit-any
  eventId?: number
  setError?: UseFormSetError<T>
}

export function useCareFormSubmit<T extends FieldValues = FieldValues>({
  createFn,
  updateFn,
  eventId,
  setError,
}: UseCareFormSubmitOptions<T>) {
  const [formError, setFormError] = useState<string | null>(null)

  const submit = useCallback(
    async (
      payload: any, // eslint-disable-line @typescript-eslint/no-explicit-any
      onSuccess?: (saved: any) => void | Promise<void> // eslint-disable-line @typescript-eslint/no-explicit-any
    ) => {
      setFormError(null)
      try {
        let saved
        if (eventId != null && updateFn) {
          saved = await updateFn({ eventId, payload })
        } else if (createFn) {
          saved = await createFn(payload)
        } else {
          throw new Error('useCareFormSubmit requires createFn or updateFn')
        }
        await onSuccess?.(saved)
      } catch (err) {
        const msg = handleApiError(err, setError)
        if (msg) setFormError(msg)
      }
    },
    [createFn, updateFn, eventId, setError]
  )

  return { submit, formError }
}
