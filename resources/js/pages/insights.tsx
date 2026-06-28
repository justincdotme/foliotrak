import { useState } from 'react'
import { BarChart3, Tag as TagIcon, MapPin } from 'lucide-react'
import { Card } from '@/components/ui/card'
import { Chip } from '@/components/app/chip'
import { EmptyState } from '@/components/app/empty-state'
import { Spinner } from '@/components/app/spinner'
import { GroupComparison } from '@/components/charts/group-comparison'
import { CorrelationPending } from '@/components/charts/correlation-pending'
import { CorrelationScatter } from '@/components/charts/correlation-scatter'
import { CorrelationHeatmap } from '@/components/charts/correlation-heatmap'
import { useTags } from '@/hooks/useTags'
import { useLocations } from '@/hooks/useLocations'
import { useGroupInsights } from '@/hooks/useGroupInsights'

export function InsightsPage() {
  const { data: tags } = useTags()
  const { data: locations } = useLocations()
  const [tagId, setTagId] = useState<number | null>(null)
  const [locationId, setLocationId] = useState<number | null>(null)

  const params = {
    ...(tagId != null && { tag: tagId }),
    ...(locationId != null && { location: locationId }),
  }

  const { data, loading, error } = useGroupInsights(params)

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold">Insights</h1>
        <p className="text-text-muted text-[13px] mt-0.5">
          Compare plants across your collection. Filter by tag, location, or both. Patterns here are
          correlational, never causal.
        </p>
      </div>
      {((tags && tags.length > 0) || locations.length > 0) && (
        <Card className="p-4 space-y-3">
          {tags && tags.length > 0 && (
            <div>
              <div className="flex items-center gap-1.5 text-[12px] font-medium text-text-muted mb-1.5">
                <TagIcon size={13} />
                Filter by tag
              </div>
              <div className="flex flex-wrap gap-1.5">
                {tags.map(t => (
                  <Chip
                    key={t.id}
                    color={t.color ?? undefined}
                    active={tagId === t.id}
                    outline={tagId !== t.id}
                    onClick={() => setTagId(tagId === t.id ? null : t.id)}
                  >
                    {t.name}
                  </Chip>
                ))}
              </div>
            </div>
          )}
          {locations.length > 0 && (
            <div>
              <div className="flex items-center gap-1.5 text-[12px] font-medium text-text-muted mb-1.5">
                <MapPin size={13} />
                Filter by location
              </div>
              <div className="flex flex-wrap gap-1.5">
                {locations.map(l => (
                  <Chip
                    key={l.id}
                    active={locationId === l.id}
                    outline={locationId !== l.id}
                    onClick={() => setLocationId(locationId === l.id ? null : l.id)}
                  >
                    {l.name}
                  </Chip>
                ))}
              </div>
            </div>
          )}
        </Card>
      )}
      {error ? (
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
            {tagId != null || locationId != null
              ? 'This filter matches fewer than 2 active plants. Broaden the selection or add more plants.'
              : 'Add at least 2 plants to start seeing insights.'}
          </EmptyState>
        </Card>
      ) : (
        <>
          <GroupComparison comparison={data.comparison} />
          {data.correlation_pairs.length === 0 ? (
            <CorrelationPending />
          ) : (
            <>
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
