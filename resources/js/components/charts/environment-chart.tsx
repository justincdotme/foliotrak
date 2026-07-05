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
  { value: 'day', label: 'Day' },
  { value: 'week', label: 'Week' },
  { value: 'month', label: 'Month' },
]

function formatXAxis(recorded_at: string, range: Range): string {
  const d = new Date(recorded_at)
  if (range === 'day') {
    return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
  }
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

// Recharts needs a single time-indexed array with all sensor series as keyed columns.
function buildChartData(sensors: SensorSeries[]) {
  const map = new Map<string, Record<string, number | string>>()

  for (const sensor of sensors) {
    for (const r of sensor.readings) {
      let row = map.get(r.recorded_at)
      if (!row) {
        row = { recorded_at: r.recorded_at }
        map.set(r.recorded_at, row)
      }
      row[`temp_${sensor.id}`] = r.temperature_f
      row[`hum_${sensor.id}`] = r.humidity
    }
  }

  return [...map.values()].sort((a, b) =>
    (a.recorded_at as string).localeCompare(b.recorded_at as string)
  )
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

  return (
    <ChartShell title="Environment" height={280}>
      <div className="mb-3">
        <Segmented value={range} onChange={v => setRange(v as Range)} options={RANGE_OPTIONS} />
      </div>
      <div style={{ height: 220 }}>
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={chartData} margin={{ top: 6, right: 8, bottom: 0, left: -6 }}>
            <CartesianGrid stroke="var(--border)" vertical={false} />
            <XAxis
              dataKey="recorded_at"
              {...axis}
              tickFormatter={(v: string) => formatXAxis(v, range)}
            />
            <YAxis yAxisId="temp" {...axis} unit="°F" />
            <YAxis yAxisId="hum" orientation="right" domain={[0, 100]} {...axis} unit="%" />
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
                          {(entry.dataKey as string).startsWith('temp_') ? '°F' : '%'}
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
                      <span className="flex items-center gap-1">
                        <span
                          className="inline-block h-0.5 w-3"
                          style={{ background: sensor.color }}
                        />
                        {sensor.name} - Temp
                      </span>
                      <span className="flex items-center gap-1">
                        <span
                          className="inline-block h-0.5 w-3 border-t border-dashed"
                          style={{ borderColor: sensor.color }}
                        />
                        {sensor.name} - Humidity
                      </span>
                    </div>
                  ))}
                </div>
              )}
            />
            {sensors.map(sensor => (
              <Line
                key={`temp_${sensor.id}`}
                yAxisId="temp"
                type="monotone"
                dataKey={`temp_${sensor.id}`}
                name={`${sensor.name} - Temp`}
                stroke={sensor.color}
                strokeWidth={2}
                dot={false}
                connectNulls
                isAnimationActive={false}
              />
            ))}
            {sensors.map(sensor => (
              <Line
                key={`hum_${sensor.id}`}
                yAxisId="hum"
                type="monotone"
                dataKey={`hum_${sensor.id}`}
                name={`${sensor.name} - Humidity`}
                stroke={sensor.color}
                strokeWidth={2}
                strokeDasharray="5 3"
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
