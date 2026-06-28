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

interface LightTrendProps {
  data: TrendPoint[]
}

export function LightTrend({ data }: LightTrendProps) {
  const d = data.map(p => ({ ...p, label: fmtDate(p.date) }))

  return (
    <ChartShell
      title="Light level"
      n={d.length}
      height={180}
      note="Observed light level on a 0 to 10 scale."
    >
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={d} margin={{ top: 6, right: 8, bottom: 0, left: -22 }}>
          <CartesianGrid stroke="var(--border)" vertical={false} />
          <XAxis dataKey="label" {...axis} />
          <YAxis domain={[0, 10]} ticks={[0, 2, 4, 6, 8, 10]} {...axis} />
          <Tooltip
            content={({ active, payload }) =>
              active && payload && payload[0] ? (
                <TipBox>
                  <span className="tnum">{payload[0].value}</span>
                </TipBox>
              ) : null
            }
          />
          <Line
            type="monotone"
            dataKey="value"
            stroke="var(--due-soon)"
            strokeWidth={2}
            dot={{ r: 3, fill: 'var(--due-soon)' }}
            isAnimationActive={false}
          />
        </LineChart>
      </ResponsiveContainer>
    </ChartShell>
  )
}
