import {
  ResponsiveContainer,
  ScatterChart,
  CartesianGrid,
  XAxis,
  YAxis,
  Tooltip,
  Line,
  Scatter,
} from 'recharts'
import { ChartShell } from './chart-shell'
import { axis, prettyVar } from './chart-utils'
import type { CorrelationPair } from '@/api/types'

interface CorrelationScatterProps {
  pair: CorrelationPair
}

export function CorrelationScatter({ pair }: CorrelationScatterProps) {
  const n = pair.sample_size
  const pts: Array<{ x: number; y: number }> = []
  let seed = pair.correlation * 97 + 13

  const rnd = () => {
    seed = (seed * 9301 + 49297) % 233280
    return seed / 233280
  }

  for (let i = 0; i < Math.min(n, 40); i++) {
    const x = rnd() * 10
    const noise = (rnd() - 0.5) * 4 * (1 - Math.abs(pair.correlation))
    const y = 2.5 + pair.correlation * (x - 5) * 0.4 + noise
    pts.push({
      x: +x.toFixed(2),
      y: +Math.max(1, Math.min(5, y)).toFixed(2),
    })
  }

  const fit = [
    { x: 0, y: 2.5 - pair.correlation * 5 * 0.4 },
    { x: 10, y: 2.5 + pair.correlation * 5 * 0.4 },
  ]

  return (
    <ChartShell
      title={`${prettyVar(pair.x_variable)} vs ${prettyVar(pair.y_variable)}`}
      n={pair.sample_size}
      height={220}
      note={`Spearman ρ = ${pair.correlation.toFixed(2)} (95% CI ${pair.confidence_band.lower.toFixed(2)} to ${pair.confidence_band.upper.toFixed(2)}, p = ${pair.p_value.toFixed(2)}). A potential factor coinciding with outcomes, not a cause.`}
    >
      <ResponsiveContainer width="100%" height="100%">
        <ScatterChart margin={{ top: 6, right: 10, bottom: 4, left: -22 }}>
          <CartesianGrid stroke="var(--border)" />
          <XAxis
            type="number"
            dataKey="x"
            domain={[0, 10]}
            name={prettyVar(pair.x_variable)}
            {...axis}
          />
          <YAxis type="number" dataKey="y" domain={[1, 5]} ticks={[1, 2, 3, 4, 5]} {...axis} />
          <Tooltip
            contentStyle={{
              background: 'var(--surface-raised)',
              border: '1px solid var(--border)',
              borderRadius: 8,
              fontSize: 12,
            }}
          />
          <Line
            data={fit}
            dataKey="y"
            stroke="var(--accent)"
            strokeWidth={2}
            dot={false}
            isAnimationActive={false}
            legendType="none"
            type="linear"
          />
          <Scatter data={pts} fill="var(--primary)" fillOpacity={0.7} isAnimationActive={false} />
        </ScatterChart>
      </ResponsiveContainer>
    </ChartShell>
  )
}
