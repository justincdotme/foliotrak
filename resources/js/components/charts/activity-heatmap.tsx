import { Card } from '@/components/ui/card'
import { NOW } from '@/lib/format'
import type { CareEvent } from '@/api/types'

interface ActivityHeatmapProps {
  events: CareEvent[]
}

const DAY = 86400000

function dateOnly(iso: string): string {
  const parts = iso.split('T')
  return parts[0] ?? iso
}

export function ActivityHeatmap({ events }: ActivityHeatmapProps) {
  const weeks = 16
  const today = new Date(NOW)
  const start = new Date(today.getTime() - (weeks * 7 - 1) * DAY)

  const counts: Record<string, number> = {}
  events.forEach(e => {
    const k = dateOnly(e.occurred_at)
    counts[k] = (counts[k] || 0) + 1
  })

  interface Cell {
    k: string
    c: number
    future: boolean
  }

  const cells: Cell[][] = []
  let max = 1

  for (let w = 0; w < weeks; w++) {
    const col: Cell[] = []
    for (let dow = 0; dow < 7; dow++) {
      const d = new Date(start.getTime() + (w * 7 + dow) * DAY)
      const k = dateOnly(d.toISOString())
      const c = counts[k] || 0
      max = Math.max(max, c)
      col.push({ k, c, future: d > today })
    }
    cells.push(col)
  }

  const shade = (c: number): string =>
    c === 0
      ? 'var(--surface-raised)'
      : `color-mix(in srgb, var(--primary) ${Math.min(100, 25 + (c / max) * 75)}%, var(--surface-raised))`

  return (
    <Card className="p-4">
      <div className="flex items-baseline gap-2 mb-3">
        <h3 className="text-[13px] font-semibold text-text">Care activity</h3>
        <span className="text-[11px] tnum text-text-subtle ml-auto">last {weeks} weeks</span>
      </div>
      <div className="flex gap-[3px] overflow-x-auto pb-1">
        {cells.map((col, i) => (
          <div key={i} className="flex flex-col gap-[3px]">
            {col.map((cell, j) => (
              <div
                key={j}
                title={`${cell.k} · ${cell.c} event${cell.c === 1 ? '' : 's'}`}
                className="w-[11px] h-[11px] rounded-[2px]"
                style={{
                  background: cell.future ? 'transparent' : shade(cell.c),
                  opacity: cell.future ? 0 : 1,
                }}
              />
            ))}
          </div>
        ))}
      </div>
      <div className="flex items-center gap-1.5 mt-2 text-[11px] text-text-subtle">
        Less
        {[0, 1, 2, 3].map(c => (
          <span
            key={c}
            className="w-[11px] h-[11px] rounded-[2px]"
            style={{
              background:
                c === 0
                  ? 'var(--surface-raised)'
                  : `color-mix(in srgb,var(--primary) ${25 + (c / 3) * 75}%, var(--surface-raised))`,
            }}
          />
        ))}
        More
      </div>
    </Card>
  )
}
