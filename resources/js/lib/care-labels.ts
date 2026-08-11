import type { CareStatus } from '@/api/types'
import { ageDays } from '@/lib/format'

function wateredLabel(daysAgo: number): { text: string; color: string } {
  if (daysAgo === 0) return { text: 'Watered today', color: 'var(--text-muted)' }
  if (daysAgo === 1) return { text: 'Watered yesterday', color: 'var(--text-muted)' }
  return { text: `Watered ${daysAgo}d ago`, color: 'var(--text-muted)' }
}

export function waterLabel(
  due: { status: CareStatus; daysLeft: number } | null,
  lastWateredAt?: string | null
): { text: string; color: string } {
  if (!due) {
    if (lastWateredAt) return wateredLabel(ageDays(lastWateredAt))
    return { text: 'No watering logged', color: 'var(--text-subtle)' }
  }
  if (lastWateredAt) {
    const daysAgo = ageDays(lastWateredAt)
    if (daysAgo < due.daysLeft) return wateredLabel(daysAgo)
  }
  if (due.status === 'overdue')
    return { text: `Water ${Math.abs(due.daysLeft)}d overdue`, color: 'var(--overdue)' }
  if (due.status === 'due-soon')
    return {
      text: due.daysLeft <= 0 ? 'Water today' : 'Water due soon',
      color: 'var(--due-soon)',
    }
  return { text: `Water in ${due.daysLeft}d`, color: 'var(--text-muted)' }
}
