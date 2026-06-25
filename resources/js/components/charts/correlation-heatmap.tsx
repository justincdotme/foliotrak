import { Card } from '@/components/ui/card'
import { prettyVar } from './chart-utils'
import type { CorrelationPair } from '@/api/types'

interface CorrelationHeatmapProps {
  pairs: CorrelationPair[]
}

export function CorrelationHeatmap({ pairs }: CorrelationHeatmapProps) {
  const cellColor = (c: number): string => {
    const a = Math.abs(c)
    const base = c >= 0 ? 'var(--primary)' : 'var(--accent)'
    return `color-mix(in srgb,${base} ${15 + a * 70}%, var(--surface-raised))`
  }

  return (
    <Card className="p-4">
      <div className="flex items-baseline gap-2 mb-1">
        <h3 className="text-[13px] font-semibold text-text">Correlation matrix</h3>
      </div>
      <p className="text-[11px] text-text-subtle mb-3">
        Potential factors coinciding with better outcomes. Each cell shows Spearman ρ, sample size,
        and significance.
      </p>
      <div className="grid gap-2" style={{ gridTemplateColumns: 'repeat(2,minmax(0,1fr))' }}>
        {pairs.map((p, i) => (
          <div
            key={i}
            className="rounded-[8px] border border-border p-3"
            style={{ background: cellColor(p.correlation) }}
          >
            <div className="text-[12px] font-medium text-text">
              {prettyVar(p.x_variable)} <span className="text-text-subtle">/</span>{' '}
              {prettyVar(p.y_variable)}
            </div>
            <div className="tnum text-2xl font-semibold mt-1 text-text">
              {p.correlation >= 0 ? '+' : ''}
              {p.correlation.toFixed(2)}
            </div>
            <div className="text-[11px] text-text-muted mt-1 tnum">
              n = {p.sample_size} · CI {p.confidence_band.lower.toFixed(2)}–
              {p.confidence_band.upper.toFixed(2)} ·{' '}
              {p.p_value < 0.05 ? 'significant' : 'not significant'} (p {p.p_value.toFixed(2)})
            </div>
          </div>
        ))}
      </div>
    </Card>
  )
}
