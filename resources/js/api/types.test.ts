import { describe, it, expect } from 'vitest'
import { gramsToWeight, weightToGrams } from './types'

describe('weight conversion at the API boundary', () => {
  it('treats null grams as an empty weight', () => {
    expect(gramsToWeight(null)).toEqual({ lb: 0, oz: 0, g: 0 })
  })

  it('splits grams into whole pounds, whole ounces, and a remainder', () => {
    // 1 lb (453.592 g) + 2 oz (56.699 g) + 5 g
    expect(gramsToWeight(515.291)).toEqual({ lb: 1, oz: 2, g: 5 })
  })

  it.each([0, 5, 200, 453.6, 1240, 2810])('round-trips %d grams within rounding', grams => {
    expect(weightToGrams(gramsToWeight(grams))).toBeCloseTo(grams, 0)
  })

  it('recombines lb/oz/g back into grams', () => {
    expect(weightToGrams({ lb: 1, oz: 0, g: 0 })).toBeCloseTo(453.6, 1)
    expect(weightToGrams({ lb: 0, oz: 1, g: 0 })).toBeCloseTo(28.3, 1)
  })
})
