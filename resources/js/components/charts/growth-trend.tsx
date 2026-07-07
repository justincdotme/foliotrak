import { useState } from 'react'
import { ResponsiveContainer, BarChart, CartesianGrid, XAxis, YAxis, Tooltip, Bar } from 'recharts'
import { GROWTH_NUM, GROWTH_LABEL } from '@/lib/domain'
import { fmtDate } from '@/lib/format'
import { DateRangeFilter } from './date-range-filter'
import { ChartShell, TipBox } from './chart-shell'
import { axis, computeTickInterval, filterByDateRange } from './chart-utils'
import type { DateRange } from './chart-utils'
import type { GrowthTrendPoint } from '@/api/types'

interface GrowthTrendProps {
  data: GrowthTrendPoint[]
}

export function GrowthTrend({ data }: GrowthTrendProps) {
  const [range, setRange] = useState<DateRange>('all')
  const filtered = filterByDateRange(data, p => p.date, range)
  const d = filtered.map(p => ({
    label: fmtDate(p.date),
    value: GROWTH_NUM[p.value || 'none'],
    raw: p.value,
  }))

  return (
    <ChartShell
      title="Growth rate"
      n={d.length}
      height={200}
      note="Observed growth pace at each check-in."
    >
      <div className="mb-3">
        <DateRangeFilter value={range} onChange={setRange} />
      </div>
      <div dusk="growth-trend-chart" style={{ height: 140 }}>
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={d} margin={{ top: 6, right: 8, bottom: 0, left: -22 }}>
            <CartesianGrid stroke="var(--border)" vertical={false} />
            <XAxis dataKey="label" {...axis} interval={computeTickInterval(d.length)} />
            <YAxis
              domain={[0, 3]}
              ticks={[0, 1, 2, 3]}
              tickFormatter={(v: number) => GROWTH_LABEL[v] ?? ''}
              {...axis}
              width={66}
            />
            <Tooltip
              content={({ active, payload }) =>
                active && payload && payload[0] ? (
                  <TipBox>
                    <span className="capitalize">{payload[0].payload.raw}</span>
                  </TipBox>
                ) : null
              }
            />
            <Bar
              dataKey="value"
              radius={[4, 4, 0, 0]}
              fill="var(--primary)"
              isAnimationActive={false}
              barSize={22}
            />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </ChartShell>
  )
}
