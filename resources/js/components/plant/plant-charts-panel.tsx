import { BarChart3 } from 'lucide-react'
import type { PlantRecommendations, PlantTimeline } from '@/api/types'
import { Card } from '@/components/ui/card'
import { EmptyState } from '@/components/app/empty-state'
import { ActivityHeatmap } from '@/components/charts/activity-heatmap'
import { GrowthTrend } from '@/components/charts/growth-trend'
import { HealthByLocation } from '@/components/charts/health-by-location'
import { HealthTrend } from '@/components/charts/health-trend'
import { LeafSizeTrend } from '@/components/charts/leaf-size-trend'
import { LightTrend } from '@/components/charts/light-trend'
import { TimelineOverlay } from '@/components/charts/timeline-overlay'
import { WeightTrend } from '@/components/charts/weight-trend'
import { usePlantChartData } from '@/hooks/usePlantChartData'

interface PlantChartsPanelProps {
  timeline: PlantTimeline | null
  recommendations: PlantRecommendations | null
}

export function PlantChartsPanel({ timeline, recommendations }: PlantChartsPanelProps) {
  const {
    events,
    healthTrend,
    weightTrend,
    growthTrend,
    lightTrend,
    leafSizeTrend,
    hasHealth,
    hasWeight,
    hasGrowth,
    hasLight,
    hasLeafSize,
  } = usePlantChartData(timeline)

  // Health-by-location reads as a comparison only with two or more spots; a single spot adds
  // nothing the health trend does not already show.
  const locationBuckets = (recommendations?.health_by_location ?? []).filter(l => l.sample_size > 0)

  return (
    <>
      {events.length === 0 ? (
        <Card>
          <EmptyState icon={BarChart3} title="Nothing to chart yet">
            Log a watering or observation to start charting activity, health, weight, and growth.
          </EmptyState>
        </Card>
      ) : (
        <div className="space-y-4">
          {hasHealth && <TimelineOverlay health={healthTrend} events={events} />}
          <div
            className="grid gap-4"
            style={{ gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))' }}
          >
            {hasHealth && <HealthTrend data={healthTrend} />}
            {hasWeight && <WeightTrend data={weightTrend} />}
            {hasGrowth && <GrowthTrend data={growthTrend} />}
            {hasLight && <LightTrend data={lightTrend} />}
            {hasLeafSize && <LeafSizeTrend data={leafSizeTrend} />}
            <ActivityHeatmap events={events} />
          </div>
        </div>
      )}

      {locationBuckets.length >= 2 && <HealthByLocation data={locationBuckets} />}
    </>
  )
}
