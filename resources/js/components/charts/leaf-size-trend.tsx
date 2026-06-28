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

interface LeafSizeTrendProps {
  data: TrendPoint[]
}

export function LeafSizeTrend({ data }: LeafSizeTrendProps) {
  const d = data.map(p => ({ ...p, label: fmtDate(p.date) }))

  return (
    <ChartShell
      title="Leaf size"
      n={d.length}
      height={180}
      note="Largest leaf measured at each check-in."
    >
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={d} margin={{ top: 6, right: 8, bottom: 0, left: -6 }}>
          <CartesianGrid stroke="var(--border)" vertical={false} />
          <XAxis dataKey="label" {...axis} />
          <YAxis {...axis} width={44} tickFormatter={(v: number) => `${v} mm`} />
          <Tooltip
            content={({ active, payload }) =>
              active && payload && payload[0] ? (
                <TipBox>
                  <span className="tnum">{payload[0].value} mm</span>
                </TipBox>
              ) : null
            }
          />
          <Line
            type="monotone"
            dataKey="value"
            stroke="var(--primary)"
            strokeWidth={2}
            dot={{ r: 3, fill: 'var(--primary)' }}
            isAnimationActive={false}
          />
        </LineChart>
      </ResponsiveContainer>
    </ChartShell>
  )
}
