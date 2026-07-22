import { ResponsiveHeatMap } from '@nivo/heatmap'
import { Card } from '@/components/ui/card'
import { pairsToHeatmapSeries } from './chart-utils'
import type { CorrelationPair } from '@/api/types'

interface CorrelationHeatmapProps {
  pairs: CorrelationPair[]
}

// Greener cells lean positive, terracotta lean negative; paler cells sit closer to no link.
const cellColor = (value: number | null): string => {
  if (value == null) return 'var(--surface-raised)'
  const base = value >= 0 ? 'var(--primary)' : 'var(--accent)'
  return `color-mix(in srgb, ${base} ${Math.round(15 + Math.abs(value) * 70)}%, var(--surface-raised))`
}

export function CorrelationHeatmap({ pairs }: CorrelationHeatmapProps) {
  const data = pairsToHeatmapSeries(pairs)

  return (
    <Card dusk="correlation-heatmap" className="p-4">
      <h3 className="text-[13px] font-semibold text-text mb-1">Correlation matrix</h3>
      <p className="text-[11px] text-text-subtle mb-3">
        Potential factors that coincided with outcomes across the group. Each cell shows the
        correlation; hover for its sample size and whether it holds up against the other factors
        tested. Patterns here may indicate, never prove.
      </p>
      <div className="h-[220px]">
        <ResponsiveHeatMap
          data={data}
          margin={{ top: 30, right: 16, bottom: 16, left: 110 }}
          valueFormat={value => value.toFixed(2)}
          colors={cell => cellColor(cell.value)}
          emptyColor="var(--surface-raised)"
          borderColor="var(--border)"
          borderWidth={1}
          enableLabels
          labelTextColor="var(--text)"
          axisTop={{ tickSize: 0, tickPadding: 6 }}
          axisLeft={{ tickSize: 0, tickPadding: 6 }}
          theme={{ text: { fontSize: 10, fill: 'var(--text-subtle)' } }}
          animate={false}
          isInteractive
          tooltip={({ cell }) => (
            <div className="rounded-[6px] border border-border bg-surface-raised px-2 py-1 text-[11px] text-text shadow-sm">
              {cell.serieId} vs {cell.data.x}:{' '}
              {cell.value == null ? 'no reading' : cell.value.toFixed(2)}
              {cell.data.n != null && <> · n = {cell.data.n}</>}
              {cell.value != null && (
                <> · {cell.data.significant ? 'holds up after adjustment' : 'could be chance'}</>
              )}
            </div>
          )}
        />
      </div>
    </Card>
  )
}
