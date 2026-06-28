import {
  ResponsiveContainer,
  ComposedChart,
  CartesianGrid,
  XAxis,
  YAxis,
  Tooltip,
  Scatter,
  Cell,
  Line,
} from 'recharts'
import { ChartShell } from './chart-shell'
import { axis, describeCorrelation, fillFromHealth, prettyVar, regression } from './chart-utils'
import type { CorrelationPair } from '@/api/types'

interface CorrelationScatterProps {
  pair: CorrelationPair
}

export function CorrelationScatter({ pair }: CorrelationScatterProps) {
  const points = pair.points.map(p => ({ x: p.x, y: p.y }))
  const band = `${pair.confidence_band.lower.toFixed(2)} to ${pair.confidence_band.upper.toFixed(2)}`
  const stats = `Correlation ${pair.correlation.toFixed(2)}, 95% range ${band}${pair.significant_after_fdr ? '' : ', not significant after adjusting for the other factors tested'}.`

  const reg = pair.significant_after_fdr && points.length >= 2 ? regression(pair.points) : null
  const xValues = points.map(p => p.x)
  const xMin = xValues.length > 0 ? Math.min(...xValues) : 0
  const xMax = xValues.length > 0 ? Math.max(...xValues) : 0
  const regLine = reg
    ? [
        { x: xMin, y: reg.slope * xMin + reg.intercept },
        { x: xMax, y: reg.slope * xMax + reg.intercept },
      ]
    : null

  return (
    <ChartShell
      title={`${prettyVar(pair.x_variable)} vs ${prettyVar(pair.y_variable)}`}
      n={pair.sample_size}
      height={220}
      note={`${describeCorrelation(pair)} ${stats}`}
    >
      <ResponsiveContainer width="100%" height="100%">
        <ComposedChart margin={{ top: 6, right: 10, bottom: 4, left: -22 }}>
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
          <Scatter data={points} fillOpacity={0.75} isAnimationActive={false}>
            {points.map((p, i) => (
              <Cell key={i} fill={fillFromHealth(p.y)} />
            ))}
          </Scatter>
          {regLine && (
            <Line
              data={regLine}
              dataKey="y"
              dot={false}
              stroke="var(--text-subtle)"
              strokeWidth={1.5}
              strokeDasharray="4 4"
              isAnimationActive={false}
            />
          )}
        </ComposedChart>
      </ResponsiveContainer>
    </ChartShell>
  )
}
