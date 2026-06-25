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
import { HEALTH_VAR } from '@/lib/domain'
import { fmtDate } from '@/lib/format'
import { ChartShell, TipBox } from './chart-shell'
import { axis } from './chart-utils'
import type { TrendPoint } from '@/api/types'

interface HealthTrendProps {
  data: TrendPoint[]
}

export function HealthTrend({ data }: HealthTrendProps) {
  const d = data.map(p => ({ ...p, label: fmtDate(p.date) }))

  return (
    <ChartShell
      title="Health trend"
      n={d.length}
      height={180}
      note="Self-reported 1–5 rating. Each point is one observation."
    >
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={d} margin={{ top: 6, right: 8, bottom: 0, left: -22 }}>
          <CartesianGrid stroke="var(--border)" vertical={false} />
          <XAxis dataKey="label" {...axis} />
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
                  fill={HEALTH_VAR[v]}
                  stroke="var(--surface)"
                  strokeWidth={2}
                />
              )
            }}
          />
        </LineChart>
      </ResponsiveContainer>
    </ChartShell>
  )
}
