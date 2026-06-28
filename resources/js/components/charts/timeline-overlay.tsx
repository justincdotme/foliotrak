import { useMemo } from 'react'
import {
  ResponsiveContainer,
  ComposedChart,
  CartesianGrid,
  XAxis,
  YAxis,
  Tooltip,
  Line,
  Scatter,
  ReferenceArea,
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

const BAND_FILLS = [
  'color-mix(in srgb,var(--primary) 5%,transparent)',
  'color-mix(in srgb,var(--info) 5%,transparent)',
]

export function TimelineOverlay({ health, events }: TimelineOverlayProps) {
  // Reconstruct location intervals from relocation events so each stint can be
  // shaded as a background band, making it easy to see if a move changed health.
  const locationBands = useMemo(() => {
    const relocations = events
      .filter(e => e.type === 'relocation' && e.relocation)
      .sort((a, b) => new Date(a.occurred_at).getTime() - new Date(b.occurred_at).getTime())

    const bands: Array<{ start: number; end: number; location: string }> = []
    for (let i = 0; i < relocations.length; i++) {
      const reloc = relocations[i]
      const next = relocations[i + 1]
      if (!reloc) continue
      const loc = reloc.relocation?.to_location?.name ?? 'Unknown'
      const start = new Date(reloc.occurred_at).getTime()
      const end = next ? new Date(next.occurred_at).getTime() : Date.now()
      bands.push({ start, end, location: loc })
    }
    return bands
  }, [events])

  // An empty health series would make the X domain [Infinity, -Infinity].
  if (health.length === 0) return null

  const d = health.map(p => ({
    x: new Date(p.date).getTime(),
    label: fmtDate(p.date),
    value: p.value,
  }))

  const markers: Record<string, Array<{ x: number; y: number; t: string }>> = {}

  ;['watering', 'fertilizing', 'repotting', 'relocation'].forEach((t: string) => {
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
          {locationBands.map((band, i) => (
            <ReferenceArea
              key={band.start}
              x1={band.start}
              x2={band.end}
              fill={BAND_FILLS[i % BAND_FILLS.length] ?? 'transparent'}
              strokeOpacity={0}
            />
          ))}
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
          <Scatter
            data={markers.relocation}
            dataKey="y"
            fill="var(--text-muted)"
            shape="triangle"
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
        <span className="flex items-center gap-1">
          <span
            className="w-2 h-2"
            style={{
              background: 'var(--text-muted)',
              clipPath: 'polygon(50% 0%, 0% 100%, 100% 100%)',
            }}
          />
          Relocation
        </span>
      </div>
    </ChartShell>
  )
}
