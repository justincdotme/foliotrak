import { describe, it, expect, vi } from 'vitest'
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

  it('shows "Watered today" when schedule exists but watering is closer than due date', () => {
    vi.useFakeTimers({ now: new Date(2026, 7, 10, 12, 0, 0) })
    const today = '2026-08-10'
    expect(waterLabel({ status: 'ok', daysLeft: 5 }, today)).toEqual({
      text: 'Watered today',
      color: 'var(--text-muted)',
    })
    vi.useRealTimers()
  })

  it('shows "Watered yesterday" when schedule exists but watering is closer than due date', () => {
    vi.useFakeTimers({ now: new Date(2026, 7, 10, 12, 0, 0) })
    const yesterday = '2026-08-09'
    expect(waterLabel({ status: 'ok', daysLeft: 4 }, yesterday)).toEqual({
      text: 'Watered yesterday',
      color: 'var(--text-muted)',
    })
    vi.useRealTimers()
  })

  it('shows "Watered 2d ago" when schedule exists and 2d ago < 3d left', () => {
    vi.useFakeTimers({ now: new Date(2026, 7, 10, 12, 0, 0) })
    const twoDaysAgo = '2026-08-08'
    expect(waterLabel({ status: 'ok', daysLeft: 3 }, twoDaysAgo)).toEqual({
      text: 'Watered 2d ago',
      color: 'var(--text-muted)',
    })
    vi.useRealTimers()
  })

  it('shows forward-looking "Water in" when watering is farther than due date', () => {
    vi.useFakeTimers({ now: new Date(2026, 7, 10, 12, 0, 0) })
    const threeDaysAgo = '2026-08-07'
    expect(waterLabel({ status: 'ok', daysLeft: 2 }, threeDaysAgo)).toEqual({
      text: 'Water in 2d',
      color: 'var(--text-muted)',
    })
    vi.useRealTimers()
  })

  it('keeps overdue label even when lastWateredAt is present', () => {
    vi.useFakeTimers({ now: new Date(2026, 7, 10, 12, 0, 0) })
    const weekAgo = '2026-08-03'
    expect(waterLabel({ status: 'overdue', daysLeft: -2 }, weekAgo)).toEqual({
      text: 'Water 2d overdue',
      color: 'var(--overdue)',
    })
    vi.useRealTimers()
  })

  it('favors the forward-looking label on an exact tie between daysAgo and daysLeft', () => {
    vi.useFakeTimers({ now: new Date(2026, 7, 10, 12, 0, 0) })
    const fourDaysAgo = '2026-08-06'
    expect(waterLabel({ status: 'ok', daysLeft: 4 }, fourDaysAgo)).toEqual({
      text: 'Water in 4d',
      color: 'var(--text-muted)',
    })
    vi.useRealTimers()
  })
})
