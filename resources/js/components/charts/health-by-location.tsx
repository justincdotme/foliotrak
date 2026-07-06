import {
  ResponsiveContainer,
  ScatterChart,
  CartesianGrid,
  XAxis,
  YAxis,
  Tooltip,
  Legend,
  Scatter,
} from 'recharts'
import { ChartShell } from './chart-shell'
import { axis } from './chart-utils'
import type { LocationHealth } from '@/api/types'

interface HealthByLocationProps {
  data: LocationHealth[]
}

import type { Location } from '@/api/types'

const label = (location: Location | null): string => location?.name ?? 'Unspecified'

// The per-location sample size rides on the axis label so each median carries its own n, since
// integer health on a categorical axis collapses repeated readings into one dot.
const tick = (d: LocationHealth): string => `${label(d.location)} (n=${d.sample_size})`

// Categorical sibling of the watering correlation scatter: location is a name, not a number, so
// there is no trend line; each reading is a dot and each location's median is marked instead.
export function HealthByLocation({ data }: HealthByLocationProps) {
  const buckets = data.filter(d => d.sample_size > 0)
  const readings = buckets.flatMap(d => d.healths.map(h => ({ location: tick(d), y: h })))
  const medians = buckets
    .filter(d => d.median_health != null)
    .map(d => ({ location: tick(d), y: d.median_health as number }))
  const total = buckets.reduce((sum, d) => sum + d.sample_size, 0)

  return (
    <ChartShell
      title="Health by location"
      n={total}
      height={240}
      note="Health readings that coincided with each spot, with each location's median marked. It shows where this plant has read healthiest, not proof a spot helps or harms; more readings per spot means a more reliable read."
    >
      <ResponsiveContainer width="100%" height="100%">
        <ScatterChart margin={{ top: 6, right: 10, bottom: 4, left: -22 }}>
          <CartesianGrid stroke="var(--border)" vertical={false} />
          <XAxis type="category" dataKey="location" allowDuplicatedCategory={false} {...axis} />
          <YAxis type="number" dataKey="y" domain={[1, 5]} ticks={[1, 2, 3, 4, 5]} {...axis} />
          <Tooltip
            cursor={{ strokeDasharray: '3 3' }}
            contentStyle={{
              background: 'var(--surface-raised)',
              border: '1px solid var(--border)',
              borderRadius: 8,
              fontSize: 12,
            }}
          />
          <Legend
            content={() => (
              <div className="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-[11px] text-text-muted">
                <div className="flex items-center gap-1">
                  <span
                    className="inline-block h-2 w-2 rounded-full"
                    style={{ background: 'var(--primary)', opacity: 0.55 }}
                  />
                  Reading
                </div>
                <div className="flex items-center gap-1">
                  <span
                    className="inline-block h-2 w-2 rotate-45"
                    style={{ background: 'var(--accent)' }}
                  />
                  Median
                </div>
              </div>
            )}
          />
          <Scatter
            name="Reading"
            data={readings}
            fill="var(--primary)"
            fillOpacity={0.55}
            isAnimationActive={false}
          />
          <Scatter
            name="Median"
            data={medians}
            fill="var(--accent)"
            shape="diamond"
            isAnimationActive={false}
          />
        </ScatterChart>
      </ResponsiveContainer>
    </ChartShell>
  )
}
