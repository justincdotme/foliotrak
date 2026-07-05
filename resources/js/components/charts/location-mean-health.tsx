import { ResponsiveContainer, BarChart, Bar, XAxis, YAxis, Cell, LabelList } from 'recharts'
import { ChartShell } from './chart-shell'
import { axis, fillFromHealth } from './chart-utils'
import type { LocationSummary } from '@/api/types'

interface LocationMeanHealthBarProps {
  data: LocationSummary[]
}

export function LocationMeanHealthBar({ data }: LocationMeanHealthBarProps) {
  const rows = data.filter(d => d.mean_health != null)
  const total = rows.reduce((s, d) => s + d.sample_size, 0)

  return (
    <ChartShell
      title="Mean health by location"
      n={total || null}
      height={Math.max(80, rows.length * 44)}
      note="Mean observed health (1 to 5) per location. Sample size shown on each bar."
    >
      <ResponsiveContainer width="100%" height="100%">
        <BarChart layout="vertical" data={rows} margin={{ top: 4, right: 52, bottom: 4, left: 8 }}>
          <XAxis type="number" domain={[0, 5]} ticks={[1, 2, 3, 4, 5]} {...axis} />
          <YAxis type="category" dataKey="location_name" width={90} {...axis} />
          <Bar dataKey="mean_health" isAnimationActive={false}>
            {rows.map((d, i) => (
              <Cell
                key={i}
                fill={fillFromHealth(d.mean_health ?? 0, 'var(--text-subtle)')}
                fillOpacity={0.82}
              />
            ))}
            <LabelList
              dataKey="sample_size"
              position="right"
              formatter={v => `n=${v ?? ''}`}
              style={{ fontSize: 11, fill: 'var(--text-subtle)' }}
            />
          </Bar>
        </BarChart>
      </ResponsiveContainer>
    </ChartShell>
  )
}
