import { useEffect, useState } from 'react'
import { BarChart3, Tag as TagIcon } from 'lucide-react'
import { Card } from '@/components/ui/card'
import { SectionTitle } from '@/components/app/section-title'
import { Chip } from '@/components/app/chip'
import { EmptyState } from '@/components/app/empty-state'
import { Spinner } from '@/components/app/spinner'
import { GroupComparison } from '@/components/charts/group-comparison'
import { CorrelationPending } from '@/components/charts/correlation-pending'
import { CorrelationScatter } from '@/components/charts/correlation-scatter'
import { CorrelationHeatmap } from '@/components/charts/correlation-heatmap'
import { useTags } from '@/hooks/useTags'
import { useGroupInsights } from '@/hooks/useGroupInsights'

export function InsightsPage() {
  const { data: tags } = useTags()
  const [tagId, setTagId] = useState<number | null>(null)
  const { data, loading, error } = useGroupInsights(tagId)

  // Default to the first available tag once tags load; DB tag ids are not
  // guaranteed to start at 1.
  useEffect(() => {
    const first = tags?.[0]
    if (tagId == null && first) setTagId(first.id)
  }, [tags, tagId])

  // No tag can ever be selected, so the query stays disabled; surface that
  // instead of an endless spinner.
  const noTags = tags != null && tags.length === 0

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
      {noTags ? (
        <Card>
          <EmptyState icon={TagIcon} title="No tags yet">
            Tag a couple of plants to compare them as a group.
          </EmptyState>
        </Card>
      ) : error ? (
        <Card>
          <EmptyState icon={BarChart3} title="Unable to load insights">
            Something went wrong fetching this group. Try again.
          </EmptyState>
        </Card>
      ) : loading || !data ? (
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
          {data.correlation_pairs.length === 0 ? (
            <CorrelationPending />
          ) : (
            <>
              {/* The matrix only reads as a matrix with two or more factors; one factor is just
                  the scatter below it, so the heatmap waits until a second factor lands. */}
              {data.correlation_pairs.length >= 2 && (
                <CorrelationHeatmap pairs={data.correlation_pairs} />
              )}
              <div
                className="grid gap-4"
                style={{ gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))' }}
              >
                {data.correlation_pairs.map((pair, i) => (
                  <CorrelationScatter
                    key={`${pair.x_variable}-${pair.y_variable}-${i}`}
                    pair={pair}
                  />
                ))}
              </div>
            </>
          )}
        </>
      )}
    </div>
  )
}
