import { describe, it, expect } from 'vitest'
import { waterLabel } from './care-labels'

describe('waterLabel', () => {
  it('returns overdue label with days count', () => {
    expect(waterLabel({ status: 'overdue', daysLeft: -3 })).toEqual({
      text: 'Water 3d overdue',
      color: 'var(--overdue)',
    })
  })

  it('returns "Water today" for due-soon with 0 days left', () => {
    expect(waterLabel({ status: 'due-soon', daysLeft: 0 })).toEqual({
      text: 'Water today',
      color: 'var(--due-soon)',
    })
  })

  it('returns "Water due soon" for due-soon with positive days', () => {
    expect(waterLabel({ status: 'due-soon', daysLeft: 2 })).toEqual({
      text: 'Water due soon',
      color: 'var(--due-soon)',
    })
  })

  it('returns days remaining for ok status', () => {
    expect(waterLabel({ status: 'ok', daysLeft: 5 })).toEqual({
      text: 'Water in 5d',
      color: 'var(--text-muted)',
    })
  })

  it('returns "No watering logged" when due is null and no last watered date', () => {
    expect(waterLabel(null)).toEqual({
      text: 'No watering logged',
      color: 'var(--text-subtle)',
    })
  })

  it('returns "Watered today" when due is null and last watered is today', () => {
    const today = new Date().toISOString().slice(0, 10)
    expect(waterLabel(null, today)).toEqual({
      text: 'Watered today',
      color: 'var(--text-muted)',
    })
  })
})
