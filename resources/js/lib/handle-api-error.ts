import { isAxiosError } from 'axios'
import type { FieldPath, FieldValues, UseFormSetError } from 'react-hook-form'

export function handleApiError<T extends FieldValues = FieldValues>(
  error: unknown,
  setError?: UseFormSetError<T>
): string {
  if (!isAxiosError(error) || !error.response) {
    return 'Something went wrong. Please try again.'
  }

  const { status, data } = error.response

  if (status === 422 && setError && data?.errors) {
    let mapped = false
    for (const [field, messages] of Object.entries(data.errors as Record<string, string[]>)) {
      const first = Array.isArray(messages) ? messages[0] : undefined
      if (first) {
        setError(field as FieldPath<T>, { message: first })
        mapped = true
      }
    }
    if (mapped) return ''
  }

  return data?.message || 'Something went wrong. Please try again.'
}
