import {
  ResponsiveContainer,
  ComposedChart,
  CartesianGrid,
  XAxis,
  YAxis,
  Tooltip,
  Line,
  Scatter,
} from 'recharts'
import { HealthBadge } from '@/components/app/health-badge'
import { fmtDate, fmtDateY } from '@/lib/format'
import { ChartShell, TipBox } from './chart-shell'
import { axis } from './chart-utils'
import type { TrendPoint, CareEvent } from '@/api/types'

interface TimelineOverlayProps {
  health: TrendPoint[]
  events: CareEvent[]
}

export function TimelineOverlay({ health, events }: TimelineOverlayProps) {
  const d = health.map(p => ({
    x: new Date(p.date).getTime(),
    label: fmtDate(p.date),
    value: p.value,
  }))

  const markers: Record<string, Array<{ x: number; y: number; t: string }>> = {}

  ;['watering', 'fertilizing', 'repotting'].forEach((t: string) => {
    markers[t] = events
      .filter((e: CareEvent) => e.type === (t as CareEvent['type']))
      .map((e: CareEvent) => ({ x: new Date(e.occurred_at).getTime(), y: 0.7, t }))
  })

  const allX = d.map(p => p.x)
  const min = Math.min(...allX)
  const max = Math.max(...allX)

  return (
    <ChartShell
      title="Care timeline overlay"
      n={d.length}
      height={200}
      note="Care events plotted against the health line. Small samples; read as context, not proof."
    >
      <ResponsiveContainer width="100%" height="100%">
        <ComposedChart margin={{ top: 6, right: 8, bottom: 0, left: -22 }}>
          <CartesianGrid stroke="var(--border)" vertical={false} />
          <XAxis
            dataKey="x"
            type="number"
            domain={[min, max]}
            scale="time"
            tickFormatter={(v: number) => fmtDate(new Date(v).toISOString())}
            {...axis}
            allowDuplicatedCategory={false}
          />
          <YAxis domain={[0, 5]} ticks={[1, 2, 3, 4, 5]} {...axis} width={28} />
          <Tooltip
            content={({ active, payload }) => {
              if (!active || !payload || !payload.length) return null
              const p = payload[0]?.payload as { value?: number; x?: number; t?: string }
              if (!p) return null
              return (
                <TipBox>
                  {p.value != null ? (
                    <HealthBadge value={p.value} />
                  ) : (
                    <span className="capitalize">{p.t}</span>
                  )}
                  <div className="text-text-subtle mt-0.5">
                    {p.x != null && fmtDateY(new Date(p.x).toISOString())}
                  </div>
                </TipBox>
              )
            }}
          />
          <Line
            data={d}
            dataKey="value"
            stroke="var(--primary)"
            strokeWidth={2}
            dot={{ r: 3 }}
            isAnimationActive={false}
            connectNulls
          />
          <Scatter
            data={markers.watering}
            dataKey="y"
            fill="var(--info)"
            shape="circle"
            isAnimationActive={false}
          />
          <Scatter
            data={markers.fertilizing}
            dataKey="y"
            fill="var(--accent)"
            shape="diamond"
            isAnimationActive={false}
          />
          <Scatter
            data={markers.repotting}
            dataKey="y"
            fill="var(--series-4)"
            shape="square"
            isAnimationActive={false}
          />
        </ComposedChart>
      </ResponsiveContainer>

      <div className="flex flex-wrap gap-3 mt-2 text-[11px] text-text-muted">
        <span className="flex items-center gap-1">
          <span className="w-2 h-2 rounded-full" style={{ background: 'var(--primary)' }} />
          Health
        </span>
        <span className="flex items-center gap-1">
          <span className="w-2 h-2 rounded-full" style={{ background: 'var(--info)' }} />
          Watering
        </span>
        <span className="flex items-center gap-1">
          <span className="w-2 h-2 rotate-45" style={{ background: 'var(--accent)' }} />
          Fertilizing
        </span>
        <span className="flex items-center gap-1">
          <span className="w-2 h-2" style={{ background: 'var(--series-4)' }} />
          Repotting
        </span>
      </div>
    </ChartShell>
  )
}
