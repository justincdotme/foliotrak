import { HttpResponse } from 'msw'

export const jsonMessage = (status: number, message: string) =>
  HttpResponse.json({ message }, { status })

export const laravelValidationError = (errors: Record<string, string[]>) =>
  HttpResponse.json({ message: 'The given data was invalid.', errors }, { status: 422 })
