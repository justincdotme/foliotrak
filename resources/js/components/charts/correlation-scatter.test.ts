import { describe, it, expect } from 'vitest'
import { fillFromHealth } from './correlation-scatter'

describe('fillFromHealth', () => {
  it.each([1, 2, 3, 4, 5])('maps integer health %s to var(--health-%s)', y => {
    expect(fillFromHealth(y)).toBe(`var(--health-${y})`)
  })

  it('clamps values below 1 to health-1', () => {
    expect(fillFromHealth(0)).toBe('var(--health-1)')
    expect(fillFromHealth(-2)).toBe('var(--health-1)')
  })

  it('clamps values above 5 to health-5', () => {
    expect(fillFromHealth(6)).toBe('var(--health-5)')
  })

  it('rounds fractional values to the nearest integer before mapping', () => {
    expect(fillFromHealth(2.4)).toBe('var(--health-2)')
    expect(fillFromHealth(2.5)).toBe('var(--health-3)')
    expect(fillFromHealth(3.6)).toBe('var(--health-4)')
  })
})
