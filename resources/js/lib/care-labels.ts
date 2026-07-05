import type { CareStatus } from '@/api/types'
import { ageDays } from '@/lib/format'

export function waterLabel(
  due: { status: CareStatus; daysLeft: number } | null,
  lastWateredAt?: string | null
): { text: string; color: string } {
  if (!due) {
    if (lastWateredAt) {
      const days = ageDays(lastWateredAt)
      if (days === 0) return { text: 'Watered today', color: 'var(--text-muted)' }
      if (days === 1) return { text: 'Watered yesterday', color: 'var(--text-muted)' }
      return { text: `Watered ${days}d ago`, color: 'var(--text-muted)' }
    }
    return { text: 'No watering logged', color: 'var(--text-subtle)' }
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
