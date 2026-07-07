import { useState } from 'react'
import {
  ResponsiveContainer,
  LineChart,
  CartesianGrid,
  XAxis,
  YAxis,
  Tooltip,
  Line,
} from 'recharts'
import { HealthBadge } from '@/components/app/health-badge'
import { fmtDate } from '@/lib/format'
import { DateRangeFilter } from './date-range-filter'
import { ChartShell, TipBox } from './chart-shell'
import { axis, computeTickInterval, fillFromHealth, filterByDateRange } from './chart-utils'
import type { DateRange } from './chart-utils'
import type { TrendPoint } from '@/api/types'

interface HealthTrendProps {
  data: TrendPoint[]
}

export function HealthTrend({ data }: HealthTrendProps) {
  const [range, setRange] = useState<DateRange>('all')
  const filtered = filterByDateRange(data, p => p.date, range)
  const d = filtered.map(p => ({ ...p, label: fmtDate(p.date) }))

  return (
    <ChartShell
      title="Health trend"
      n={d.length}
      height={220}
      note="Self-reported 1-5 rating. Each point is one observation."
    >
      <div className="mb-3">
        <DateRangeFilter value={range} onChange={setRange} />
      </div>
      <div dusk="health-trend-chart" style={{ height: 160 }}>
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={d} margin={{ top: 6, right: 8, bottom: 0, left: -22 }}>
            <CartesianGrid stroke="var(--border)" vertical={false} />
            <XAxis dataKey="label" {...axis} interval={computeTickInterval(d.length)} />
            <YAxis domain={[1, 5]} ticks={[1, 2, 3, 4, 5]} {...axis} />
            <Tooltip
              content={({ active, payload }) =>
                active && payload && payload[0] ? (
                  <TipBox>
                    <HealthBadge value={payload[0].value as number} />
                  </TipBox>
                ) : null
              }
            />
            <Line
              type="monotone"
              dataKey="value"
              stroke="var(--primary)"
              strokeWidth={2}
              isAnimationActive={false}
              dot={(props: {
                cx?: number
                cy?: number
                index?: number
                payload?: { value: number }
              }) => {
                const v = props.payload?.value ?? 0
                return (
                  <circle
                    key={props.index}
                    cx={props.cx}
                    cy={props.cy}
                    r={5}
                    fill={fillFromHealth(v)}
                    stroke="var(--surface)"
                    strokeWidth={2}
                  />
                )
              }}
            />
          </LineChart>
        </ResponsiveContainer>
      </div>
    </ChartShell>
  )
}
