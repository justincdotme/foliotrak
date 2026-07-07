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
import { SERIES } from '@/lib/domain'
import { fmtDate } from '@/lib/format'
import { EmptyState } from '@/components/app/empty-state'
import { Segmented } from '@/components/app/segmented'
import { ChartShell } from './chart-shell'
import { PlantFilter } from './plant-filter'
import {
  axis,
  computeTickInterval,
  filterByWindow,
  GROUP_CHART_WINDOW_OPTIONS,
  type GroupChartWindow,
} from './chart-utils'
import type { GroupComparison } from '@/api/types'

interface GroupComparisonProps {
  comparison: GroupComparison[]
}

function colorFor(index: number): string {
  return SERIES[index % SERIES.length] ?? 'var(--series-1)'
}

export function GroupComparison({ comparison }: GroupComparisonProps) {
  const groupKey = comparison
    .map(c => c.plant_id)
    .sort((a, b) => a - b)
    .join(',')

  const [window, setWindow] = useState<GroupChartWindow>('year')
  const [lastGroupKey, setLastGroupKey] = useState(groupKey)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(
    () => new Set(comparison.map(c => c.plant_id))
  )

  if (groupKey !== lastGroupKey) {
    setLastGroupKey(groupKey)
    setSelectedIds(new Set(comparison.map(c => c.plant_id)))
  }

  const plantOptions = comparison.map((c, i) => ({
    id: c.plant_id,
    name: c.common_name || 'Unnamed',
    color: colorFor(i),
  }))

  const windowed = comparison
    .map((c, i) => ({
      ...c,
      color: colorFor(i),
      health_trend: filterByWindow(c.health_trend, p => p.date, window),
    }))
    .filter(c => selectedIds.has(c.plant_id))

  const dates = Array.from(new Set(windowed.flatMap(c => c.health_trend.map(p => p.date)))).sort()

  const data = dates.map(date => {
    const row: Record<string, string | number | null> = { date, label: fmtDate(date) }
    windowed.forEach(c => {
      const pt = c.health_trend.find(p => p.date === date)
      row[`p${c.plant_id}`] = pt ? pt.value : null
    })
    return row
  })

  const n = windowed.reduce((a, c) => a + c.health_trend.length, 0)

  return (
    <ChartShell
      title="Health trend across the group"
      n={n}
      height={320}
      note="Each line is one plant. Bands show ±1 rating of uncertainty; lines may indicate, not prove, group patterns."
    >
      <div className="flex h-full flex-col">
        <div className="flex items-center justify-between gap-2 mb-2">
          <PlantFilter plants={plantOptions} selected={selectedIds} onChange={setSelectedIds} />
          <Segmented
            value={window}
            onChange={v => setWindow(v as GroupChartWindow)}
            options={GROUP_CHART_WINDOW_OPTIONS}
            dusk="group-window"
          />
        </div>
        {windowed.length === 0 ? (
          <div className="flex-1 min-h-0 grid place-items-center">
            <EmptyState title="No plants selected">
              Pick at least one plant from the filter above.
            </EmptyState>
          </div>
        ) : (
          <>
            <div dusk="group-comparison-chart" className="flex-1 min-h-0">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={data} margin={{ top: 6, right: 8, bottom: 0, left: -22 }}>
                  <CartesianGrid stroke="var(--border)" vertical={false} />
                  <XAxis dataKey="label" {...axis} interval={computeTickInterval(data.length)} />
                  <YAxis domain={[1, 5]} ticks={[1, 2, 3, 4, 5]} {...axis} />
                  <Tooltip
                    contentStyle={{
                      background: 'var(--surface-raised)',
                      border: '1px solid var(--border)',
                      borderRadius: 8,
                      fontSize: 12,
                    }}
                  />
                  {windowed.map(c => (
                    <Line
                      key={c.plant_id}
                      dataKey={`p${c.plant_id}`}
                      name={c.common_name || 'Unnamed'}
                      stroke={c.color}
                      strokeWidth={2}
                      dot={{ r: 3 }}
                      connectNulls
                      isAnimationActive={false}
                    />
                  ))}
                </LineChart>
              </ResponsiveContainer>
            </div>
            <div className="shrink-0 max-h-14 overflow-y-auto flex flex-wrap gap-x-4 gap-y-1 mt-2 text-[11px] text-text-muted">
              {windowed.map(c => (
                <div key={c.plant_id} className="flex items-center gap-1">
                  <span className="inline-block h-0.5 w-3" style={{ backgroundColor: c.color }} />
                  {c.common_name || 'Unnamed'}
                </div>
              ))}
            </div>
          </>
        )}
      </div>
    </ChartShell>
  )
}
