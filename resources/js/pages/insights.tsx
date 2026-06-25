import { useState } from 'react'
import { BarChart3, Tag as TagIcon } from 'lucide-react'
import { Card } from '@/components/ui/card'
import { SectionTitle } from '@/components/app/section-title'
import { Chip } from '@/components/app/chip'
import { EmptyState } from '@/components/app/empty-state'
import { Spinner } from '@/components/app/spinner'
import { GroupComparison } from '@/components/charts/group-comparison'
import { CorrelationScatter } from '@/components/charts/correlation-scatter'
import { CorrelationHeatmap } from '@/components/charts/correlation-heatmap'
import { useTags } from '@/hooks/useTags'
import { useGroupInsights } from '@/hooks/useGroupInsights'

export function InsightsPage() {
  const { data: tags } = useTags()
  const [tagId, setTagId] = useState(1)
  const { data, loading } = useGroupInsights(tagId)

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold">Insights</h1>
        <p className="text-text-muted text-[13px] mt-0.5">
          Compare plants that share a tag. Patterns here are correlational, never causal.
        </p>
      </div>
      <Card className="p-4">
        <SectionTitle icon={TagIcon}>Group by tag</SectionTitle>
        <div className="flex flex-wrap gap-1.5">
          {(tags || []).map(t => (
            <Chip
              key={t.id}
              color={t.color ?? undefined}
              active={tagId === t.id}
              outline={tagId !== t.id}
              onClick={() => setTagId(t.id)}
            >
              {t.name}
            </Chip>
          ))}
        </div>
      </Card>
      {loading || !data ? (
        <Spinner />
      ) : data.plants.length < 2 ? (
        <Card>
          <EmptyState icon={BarChart3} title="Need at least 2 plants">
            Tag more plants with &quot;{data.tag_name}&quot; to compare them.
          </EmptyState>
        </Card>
      ) : (
        <>
          <GroupComparison comparison={data.comparison} />
          <div
            className="grid gap-4"
            style={{ gridTemplateColumns: 'repeat(auto-fit,minmax(300px,1fr))' }}
          >
            {data.correlation_pairs.slice(0, 2).map((p, i) => (
              <CorrelationScatter key={i} pair={p} />
            ))}
          </div>
          <CorrelationHeatmap pairs={data.correlation_pairs} />
        </>
      )}
    </div>
  )
}
