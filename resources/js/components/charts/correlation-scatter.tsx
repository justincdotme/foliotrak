import {
  ResponsiveContainer,
  ScatterChart,
  CartesianGrid,
  XAxis,
  YAxis,
  Tooltip,
  Scatter,
} from 'recharts'
import { ChartShell } from './chart-shell'
import { axis, describeCorrelation, prettyVar } from './chart-utils'
import type { CorrelationPair } from '@/api/types'

interface CorrelationScatterProps {
  pair: CorrelationPair
}

export function CorrelationScatter({ pair }: CorrelationScatterProps) {
  const points = pair.points.map(p => ({ x: p.x, y: p.y }))
  const band = `${pair.confidence_band.lower.toFixed(2)} to ${pair.confidence_band.upper.toFixed(2)}`
  const stats = `Correlation ${pair.correlation.toFixed(2)}, 95% range ${band}${pair.significant_after_fdr ? '' : ', not significant after adjusting for the other factors tested'}.`

  return (
    <ChartShell
      title={`${prettyVar(pair.x_variable)} vs ${prettyVar(pair.y_variable)}`}
      n={pair.sample_size}
      height={220}
      note={`${describeCorrelation(pair)} ${stats}`}
    >
      <ResponsiveContainer width="100%" height="100%">
        <ScatterChart margin={{ top: 6, right: 10, bottom: 4, left: -22 }}>
          <CartesianGrid stroke="var(--border)" />
          <XAxis
            type="number"
            dataKey="x"
            name={prettyVar(pair.x_variable)}
            domain={['auto', 'auto']}
            allowDecimals={false}
            {...axis}
          />
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
          <Scatter
            data={points}
            fill="var(--primary)"
            fillOpacity={0.7}
            isAnimationActive={false}
          />
        </ScatterChart>
      </ResponsiveContainer>
    </ChartShell>
  )
}
