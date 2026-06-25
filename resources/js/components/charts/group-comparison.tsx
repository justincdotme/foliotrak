import {
  ResponsiveContainer,
  LineChart,
  CartesianGrid,
  XAxis,
  YAxis,
  Tooltip,
  Line,
  Legend,
} from 'recharts'
import { SERIES } from '@/lib/domain'
import { fmtDate } from '@/lib/format'
import { ChartShell } from './chart-shell'
import { axis } from './chart-utils'
import type { GroupComparison } from '@/api/types'

interface GroupComparisonProps {
  comparison: GroupComparison[]
}

export function GroupComparison({ comparison }: GroupComparisonProps) {
  const dates = Array.from(new Set(comparison.flatMap(c => c.health_trend.map(p => p.date)))).sort()

  const data = dates.map(date => {
    const row: Record<string, string | number | null> = { date, label: fmtDate(date) }
    comparison.forEach(c => {
      const pt = c.health_trend.find(p => p.date === date)
      row[`p${c.plant_id}`] = pt ? pt.value : null
    })
    return row
  })

  return (
    <ChartShell
      title="Health trend across the group"
      n={comparison.reduce((a, c) => a + c.health_trend.length, 0)}
      height={240}
      note="Each line is one plant. Bands show ±1 rating of uncertainty; lines may indicate, not prove, group patterns."
    >
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={data} margin={{ top: 6, right: 8, bottom: 0, left: -22 }}>
          <CartesianGrid stroke="var(--border)" vertical={false} />
          <XAxis dataKey="label" {...axis} />
          <YAxis domain={[1, 5]} ticks={[1, 2, 3, 4, 5]} {...axis} />
          <Tooltip
            contentStyle={{
              background: 'var(--surface-raised)',
              border: '1px solid var(--border)',
              borderRadius: 8,
              fontSize: 12,
            }}
          />
          <Legend wrapperStyle={{ fontSize: 12 }} />
          {comparison.map((c, i) => (
            <Line
              key={c.plant_id}
              dataKey={`p${c.plant_id}`}
              name={c.common_name || 'Unnamed'}
              stroke={SERIES[i % 6]}
              strokeWidth={2}
              dot={{ r: 3 }}
              connectNulls
              isAnimationActive={false}
            />
          ))}
        </LineChart>
      </ResponsiveContainer>
    </ChartShell>
  )
}
