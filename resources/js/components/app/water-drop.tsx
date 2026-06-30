import { useId } from 'react'
import { Droplets } from 'lucide-react'
import type { CareStatus } from '@/api/types'

interface WaterDropProps {
  due: {
    status: CareStatus
    daysLeft: number
    interval: number
  } | null
  size?: number
  dusk?: string
}

export function WaterDrop({ due, size = 26, dusk }: WaterDropProps) {
  const id = useId().replace(/:/g, '')

  if (!due) {
    return (
      <span
        dusk={dusk}
        className="inline-grid place-items-center text-text-subtle"
        style={{ width: size, height: size }}
        title="No watering logged"
      >
        <Droplets size={size * 0.7} />
      </span>
    )
  }

  const c =
    due.status === 'overdue'
      ? 'var(--overdue)'
      : due.status === 'due-soon'
        ? 'var(--due-soon)'
        : 'var(--info)'

  const level = Math.max(0.06, Math.min(1, due.interval ? due.daysLeft / due.interval : 0.5))
  const fillTop = 23 - level * 18

  const tip =
    due.status === 'overdue'
      ? `${Math.abs(due.daysLeft)}d overdue for water`
      : due.daysLeft === 0
        ? 'water today'
        : `water in ${due.daysLeft}d`

  return (
    <span
      dusk={dusk}
      className="relative inline-grid place-items-center"
      title={tip}
      style={{ width: size, height: size }}
    >
      <svg width={size} height={size} viewBox="0 0 24 24" aria-hidden="true">
        <defs>
          <clipPath id={id}>
            <path d="M12 2.5 C12 2.5 4.5 11 4.5 16 a7.5 7.5 0 0 0 15 0 C19.5 11 12 2.5 12 2.5 Z" />
          </clipPath>
        </defs>
        <rect
          x="0"
          y={fillTop}
          width="24"
          height="24"
          fill={c}
          clipPath={`url(#${id})`}
          opacity="0.9"
        />
        <path
          d="M12 2.5 C12 2.5 4.5 11 4.5 16 a7.5 7.5 0 0 0 15 0 C19.5 11 12 2.5 12 2.5 Z"
          fill="none"
          stroke={c}
          strokeWidth="1.6"
        />
      </svg>
      {due.status === 'overdue' && (
        <span
          className="absolute -right-0.5 -top-0.5 w-3.5 h-3.5 rounded-full grid place-items-center text-white text-[9px] font-bold leading-none ring-2 ring-surface"
          style={{ background: 'var(--overdue)' }}
        >
          !
        </span>
      )}
    </span>
  )
}
