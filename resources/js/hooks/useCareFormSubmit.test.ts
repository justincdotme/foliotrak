import { renderHook, act } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'
import { useCareFormSubmit } from './useCareFormSubmit'

describe('useCareFormSubmit', () => {
  it('calls createFn and onSuccess when no eventId', async () => {
    const createFn = vi.fn().mockResolvedValue({ id: 42 })
    const onSuccess = vi.fn()
    const { result } = renderHook(() => useCareFormSubmit({ createFn }))

    await act(() => result.current.submit({ note: 'test' }, onSuccess))

    expect(createFn).toHaveBeenCalledWith({ note: 'test' })
    expect(onSuccess).toHaveBeenCalledWith({ id: 42 })
    expect(result.current.formError).toBeNull()
  })

  it('calls updateFn when eventId is provided', async () => {
    const updateFn = vi.fn().mockResolvedValue({ id: 1 })
    const { result } = renderHook(() => useCareFormSubmit({ updateFn, eventId: 1 }))

    await act(() => result.current.submit({ note: 'edited' }))

    expect(updateFn).toHaveBeenCalledWith({ eventId: 1, payload: { note: 'edited' } })
  })

  it('sets formError on non-validation failure', async () => {
    const createFn = vi.fn().mockRejectedValue(new Error('network'))
    const { result } = renderHook(() => useCareFormSubmit({ createFn }))

    await act(() => result.current.submit({}))

    expect(result.current.formError).toBe('Something went wrong. Please try again.')
  })

  it('clears formError before each submit', async () => {
    const createFn = vi
      .fn()
      .mockRejectedValueOnce(new Error('fail'))
      .mockResolvedValueOnce({ id: 2 })
    const { result } = renderHook(() => useCareFormSubmit({ createFn }))

    await act(() => result.current.submit({}))
    expect(result.current.formError).toBeTruthy()

    await act(() => result.current.submit({}))
    expect(result.current.formError).toBeNull()
  })
})
