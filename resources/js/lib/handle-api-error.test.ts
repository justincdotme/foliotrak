import { describe, it, expect } from 'vitest'
import { AxiosError, AxiosHeaders } from 'axios'
import { extractValidationError } from './handle-api-error'

function make422(errors: Record<string, string[]>) {
  return new AxiosError('Validation', '422', undefined, undefined, {
    status: 422,
    data: { message: 'The given data was invalid.', errors },
    statusText: 'Unprocessable Entity',
    headers: {},
    config: { headers: new AxiosHeaders() },
  })
}

describe('extractValidationError', () => {
  it('extracts the first message for the named field', () => {
    const err = make422({ name: ['The name has already been taken.', 'Max 64 chars.'] })
    expect(extractValidationError(err, 'name')).toBe('The name has already been taken.')
  })

  it('returns fallback when the field has no errors', () => {
    const err = make422({ other: ['Unrelated error'] })
    expect(extractValidationError(err, 'name', 'Custom fallback')).toBe('Custom fallback')
  })

  it('returns fallback for non-axios errors', () => {
    expect(extractValidationError(new Error('boom'), 'name', 'Oops')).toBe('Oops')
  })

  it('tries fields in order and returns the first match', () => {
    const err = make422({ mac: ['MAC already registered.'] })
    expect(extractValidationError(err, ['name', 'mac'], 'Fallback')).toBe('MAC already registered.')
  })

  it('returns the default fallback when none is provided', () => {
    expect(extractValidationError(new Error('boom'), 'name')).toBe(
      'Something went wrong. Please try again.'
    )
  })

  it('returns fallback for non-422 responses', () => {
    const err = new AxiosError('Server error', '500', undefined, undefined, {
      status: 500,
      data: { message: 'Internal server error' },
      statusText: 'Internal Server Error',
      headers: {},
      config: { headers: new AxiosHeaders() },
    })
    expect(extractValidationError(err, 'name', 'Failed')).toBe('Failed')
  })
})
