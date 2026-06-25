import {
  ResponsiveContainer,
  LineChart,
  CartesianGrid,
  XAxis,
  YAxis,
  Tooltip,
  Line,
} from 'recharts'
import { fmtDate } from '@/lib/format'
import { ChartShell, TipBox } from './chart-shell'
import { axis } from './chart-utils'
import type { TrendPoint } from '@/api/types'

interface WeightTrendProps {
  data: TrendPoint[]
}

export function WeightTrend({ data }: WeightTrendProps) {
  const d = data.map(p => ({ ...p, label: fmtDate(p.date) }))

  return (
    <ChartShell
      title="Weight trend"
      n={d.length}
      height={180}
      note="Pot + plant weight in grams; a proxy for soil moisture and growth."
    >
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={d} margin={{ top: 6, right: 8, bottom: 0, left: -6 }}>
          <CartesianGrid stroke="var(--border)" vertical={false} />
          <XAxis dataKey="label" {...axis} />
          <YAxis
            {...axis}
            width={44}
            tickFormatter={(v: number) => (v >= 1000 ? `${(v / 1000).toFixed(1)}kg` : `${v}g`)}
          />
          <Tooltip
            content={({ active, payload }) =>
              active && payload && payload[0] ? (
                <TipBox>
                  <span className="tnum">{payload[0].value} g</span>
                </TipBox>
              ) : null
            }
          />
          <Line
            type="monotone"
            dataKey="value"
            stroke="var(--series-4)"
            strokeWidth={2}
            dot={{ r: 3, fill: 'var(--series-4)' }}
            isAnimationActive={false}
          />
        </LineChart>
      </ResponsiveContainer>
    </ChartShell>
  )
}
