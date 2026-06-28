import { LineChart, Line } from 'recharts'
import { HEALTH_VAR } from '@/lib/domain'
import type { GroupComparison } from '@/api/types'

interface HealthSparklinesProps {
  comparison: GroupComparison[]
}

export function HealthSparklines({ comparison }: HealthSparklinesProps) {
  return (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fill, minmax(120px, 1fr))',
        gap: 12,
      }}
    >
      {comparison.map(c => {
        const d = c.health_trend.map(p => ({ v: p.value }))
        return (
          <div key={c.plant_id} className="flex flex-col items-center">
            <LineChart
              width={120}
              height={80}
              data={d}
              margin={{ top: 2, right: 2, bottom: 2, left: 2 }}
            >
              <Line
                type="monotone"
                dataKey="v"
                stroke="var(--primary)"
                strokeWidth={1.5}
                dot={(props: {
                  cx?: number
                  cy?: number
                  index?: number
                  payload?: { v: number }
                }) => {
                  const v = props.payload?.v ?? 0
                  const key = Math.min(5, Math.max(1, Math.round(v)))
                  return (
                    <circle
                      key={props.index}
                      cx={props.cx}
                      cy={props.cy}
                      r={3}
                      fill={HEALTH_VAR[key] ?? 'var(--primary)'}
                    />
                  )
                }}
                isAnimationActive={false}
              />
            </LineChart>
            <span className="text-[11px] text-text-muted mt-0.5">{c.common_name ?? 'Unnamed'}</span>
          </div>
        )
      })}
    </div>
  )
}
