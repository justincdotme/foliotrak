import { useState } from 'react'
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
import { Thermometer } from 'lucide-react'
import { useSensorReadings } from '@/hooks/useSensorReadings'
import { EmptyState } from '@/components/app/empty-state'
import { Segmented } from '@/components/app/segmented'
import { Spinner } from '@/components/app/spinner'
import { ChartShell, TipBox } from './chart-shell'
import { axis } from './chart-utils'
import type { SensorSeries } from '@/api/types'

interface EnvironmentChartProps {
  plantId: number
}

type Range = 'day' | 'week' | 'month'

const RANGE_OPTIONS = [
  { value: 'day', label: 'Day', dusk: 'env-granularity-day' },
  { value: 'week', label: 'Week', dusk: 'env-granularity-week' },
  { value: 'month', label: 'Month', dusk: 'env-granularity-month' },
]

function formatXAxis(recorded_at: string, range: Range): string {
  const d = new Date(recorded_at)
  if (range === 'day') {
    return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
  }
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

// Recharts needs a single time-indexed array with every sensor field as a keyed column.
function buildChartData(sensors: SensorSeries[]) {
  const map = new Map<string, Record<string, number | string | undefined>>()

  for (const sensor of sensors) {
    for (const r of sensor.readings) {
      let row = map.get(r.recorded_at)
      if (!row) {
        row = { recorded_at: r.recorded_at }
        map.set(r.recorded_at, row)
      }
      for (const field of sensor.fields) {
        row[`${field.key}_${sensor.id}`] = r[field.key]
      }
    }
  }

  return [...map.values()].sort((a, b) =>
    (a.recorded_at as string).localeCompare(b.recorded_at as string)
  )
}

interface AxisSpec {
  id: string
  orientation: 'left' | 'right'
  unit: string
}

// Y axes are derived from the fields present since any sensor type can place
// a field on either side.
function collectAxes(sensors: SensorSeries[]): AxisSpec[] {
  const byId = new Map<string, AxisSpec>()
  for (const sensor of sensors) {
    for (const field of sensor.fields) {
      if (!byId.has(field.axis)) {
        byId.set(field.axis, {
          id: field.axis,
          orientation: field.axis === 'right' ? 'right' : 'left',
          unit: field.unit,
        })
      }
    }
  }
  return [...byId.values()]
}

interface LineSpec {
  dataKey: string
  yAxisId: string
  name: string
  color: string
  unit: string
  dashed: boolean
}

// Grouped by field position rather than by sensor so every sensor's first field
// (e.g. temperature) draws before any second field, keeping a consistent stacking
// and tooltip order.
function collectLines(sensors: SensorSeries[]): LineSpec[] {
  const fieldCount = Math.max(0, ...sensors.map(s => s.fields.length))
  const lines: LineSpec[] = []

  for (let index = 0; index < fieldCount; index++) {
    for (const sensor of sensors) {
      const field = sensor.fields[index]
      if (!field) continue
      lines.push({
        dataKey: `${field.key}_${sensor.id}`,
        yAxisId: field.axis,
        name: `${sensor.name} - ${field.label}`,
        color: sensor.color,
        unit: field.unit,
        dashed: index > 0,
      })
    }
  }

  return lines
}

export function EnvironmentChart({ plantId }: EnvironmentChartProps) {
  const [range, setRange] = useState<Range>('week')
  const { data, isPending } = useSensorReadings(plantId, range)

  if (isPending) {
    return (
      <ChartShell title="Environment" height={280}>
        <div className="flex h-full items-center justify-center">
          <Spinner />
        </div>
      </ChartShell>
    )
  }

  const sensors = data?.sensors ?? []
  const hasReadings = sensors.some(s => s.readings.length > 0)

  if (sensors.length === 0) {
    return (
      <ChartShell title="Environment" height={280}>
        <EmptyState icon={Thermometer} title="No sensors linked">
          No sensors associated with this plant. Add sensors in the plant settings.
        </EmptyState>
      </ChartShell>
    )
  }

  if (!hasReadings) {
    return (
      <ChartShell title="Environment" height={280}>
        <div className="mb-3">
          <Segmented value={range} onChange={v => setRange(v as Range)} options={RANGE_OPTIONS} />
        </div>
        <EmptyState icon={Thermometer} title="No readings yet">
          No readings recorded yet. Readings are collected every {data?.granularity_minutes ?? '?'}{' '}
          minutes.
        </EmptyState>
      </ChartShell>
    )
  }

  const chartData = buildChartData(sensors)
  const axes = collectAxes(sensors)
  const lines = collectLines(sensors)
  const unitByKey = new Map(lines.map(l => [l.dataKey, l.unit]))

  return (
    <ChartShell title="Environment" height={280}>
      <div className="mb-3">
        <Segmented value={range} onChange={v => setRange(v as Range)} options={RANGE_OPTIONS} />
      </div>
      <div dusk="environment-chart" style={{ height: 220 }}>
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={chartData} margin={{ top: 6, right: 8, bottom: 0, left: -6 }}>
            <CartesianGrid stroke="var(--border)" vertical={false} />
            <XAxis
              dataKey="recorded_at"
              {...axis}
              tickFormatter={(v: string) => formatXAxis(v, range)}
            />
            {axes.map(a => (
              <YAxis
                key={a.id}
                yAxisId={a.id}
                orientation={a.orientation}
                domain={a.unit === '%' ? [0, 100] : undefined}
                {...axis}
                unit={a.unit}
              />
            ))}
            <Tooltip
              content={({ active, payload, label }) => {
                if (!active || !payload?.length) return null
                return (
                  <TipBox>
                    <div className="text-[11px] text-text-subtle mb-1">
                      {formatXAxis(label as string, range)}
                    </div>
                    {payload.map(entry => (
                      <div key={entry.dataKey as string} className="flex items-center gap-1.5">
                        <span
                          className="inline-block h-2 w-2 rounded-full"
                          style={{ background: entry.color }}
                        />
                        <span className="text-[12px] tnum">
                          {entry.name}: {entry.value}
                          {unitByKey.get(entry.dataKey as string) ?? ''}
                        </span>
                      </div>
                    ))}
                  </TipBox>
                )
              }}
            />
            <Legend
              content={() => (
                <div className="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-[11px] text-text-muted">
                  {sensors.map(sensor => (
                    <div key={sensor.id} className="flex items-center gap-3">
                      {sensor.fields.map((field, index) => (
                        <span key={field.key} className="flex items-center gap-1">
                          <span
                            className={
                              index === 0
                                ? 'inline-block h-0.5 w-3'
                                : 'inline-block h-0.5 w-3 border-t border-dashed'
                            }
                            style={
                              index === 0
                                ? { background: sensor.color }
                                : { borderColor: sensor.color }
                            }
                          />
                          {sensor.name} - {field.label}
                        </span>
                      ))}
                    </div>
                  ))}
                </div>
              )}
            />
            {lines.map(line => (
              <Line
                key={line.dataKey}
                yAxisId={line.yAxisId}
                type="monotone"
                dataKey={line.dataKey}
                name={line.name}
                stroke={line.color}
                strokeWidth={2}
                strokeDasharray={line.dashed ? '5 3' : undefined}
                dot={false}
                connectNulls
                isAnimationActive={false}
              />
            ))}
          </LineChart>
        </ResponsiveContainer>
      </div>
    </ChartShell>
  )
}
