import { describe, expect, it } from 'vitest'
import { plantInvalidationKeys } from './invalidation'

describe('plantInvalidationKeys', () => {
  it('returns every plant-scoped query key for a given plant id', () => {
    expect(plantInvalidationKeys(42)).toEqual([
      ['timeline', 42],
      ['plant', 42],
      ['plants'],
      ['recommendations', 42],
      ['dashboard'],
    ])
  })
})
