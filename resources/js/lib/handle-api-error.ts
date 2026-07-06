import { isAxiosError } from 'axios'
import type { FieldPath, FieldValues, UseFormSetError } from 'react-hook-form'
import { SessionExpiredError } from './errors'

export function handleApiError<T extends FieldValues = FieldValues>(
  error: unknown,
  setError?: UseFormSetError<T>
): string {
  if (error instanceof SessionExpiredError) {
    return 'Your session expired. Redirecting...'
  }

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

export function extractValidationError(
  error: unknown,
  fields: string | string[],
  fallback = 'Something went wrong. Please try again.'
): string {
  if (!isAxiosError(error) || !error.response) return fallback
  const { status, data } = error.response
  if (status === 422 && data?.errors) {
    const fieldList = Array.isArray(fields) ? fields : [fields]
    for (const field of fieldList) {
      const messages = data.errors[field]
      if (Array.isArray(messages) && messages[0]) return messages[0]
    }
  }
  return fallback
}
