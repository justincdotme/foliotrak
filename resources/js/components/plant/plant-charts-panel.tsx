import { BarChart3 } from 'lucide-react'
import type { PlantRecommendations, PlantTimeline } from '@/api/types'
import { Card } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { EmptyState } from '@/components/app/empty-state'
import { ActivityHeatmap } from '@/components/charts/activity-heatmap'
import { EnvironmentChart } from '@/components/charts/environment-chart'
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

  const locationBuckets = (recommendations?.health_by_location ?? []).filter(l => l.sample_size > 0)
  const showLocationTab = locationBuckets.length >= 2
  const plantId = timeline?.plant?.id ?? null

  if (events.length === 0) {
    return (
      <Card>
        <EmptyState icon={BarChart3} title="Nothing to chart yet">
          Log a watering or observation to start charting activity, health, weight, and growth.
        </EmptyState>
      </Card>
    )
  }

  return (
    <Tabs defaultValue="trends">
      <TabsList className="overflow-x-auto w-full justify-start">
        <TabsTrigger value="trends">Trends</TabsTrigger>
        <TabsTrigger value="activity">Activity</TabsTrigger>
        <TabsTrigger value="light">Light</TabsTrigger>
        <TabsTrigger value="environment">Environment</TabsTrigger>
        {showLocationTab && <TabsTrigger value="location">Location</TabsTrigger>}
      </TabsList>

      <TabsContent value="trends" forceMount className="data-[state=inactive]:hidden">
        <div className="space-y-4">
          {hasHealth && <TimelineOverlay health={healthTrend} events={events} />}
          <div
            className="grid gap-4"
            style={{ gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))' }}
          >
            {hasHealth && <HealthTrend data={healthTrend} />}
            {hasWeight && <WeightTrend data={weightTrend} />}
            {hasGrowth && <GrowthTrend data={growthTrend} />}
            {hasLeafSize && <LeafSizeTrend data={leafSizeTrend} />}
          </div>
        </div>
      </TabsContent>

      <TabsContent value="activity" forceMount className="data-[state=inactive]:hidden">
        <div className="space-y-4">
          <ActivityHeatmap events={events} />
        </div>
      </TabsContent>

      <TabsContent value="light" forceMount className="data-[state=inactive]:hidden">
        <div className="space-y-4">
          {hasLight && <LightTrend data={lightTrend} />}
          {!hasLight && (
            <Card>
              <EmptyState icon={BarChart3} title="No light data">
                Record a light level in an observation to see the trend here.
              </EmptyState>
            </Card>
          )}
        </div>
      </TabsContent>

      <TabsContent value="environment" forceMount className="data-[state=inactive]:hidden">
        {plantId ? (
          <EnvironmentChart plantId={plantId} />
        ) : (
          <Card>
            <EmptyState icon={BarChart3} title="No sensor data">
              No sensors associated with this plant. Add sensors in the plant settings.
            </EmptyState>
          </Card>
        )}
      </TabsContent>

      {showLocationTab && (
        <TabsContent value="location" forceMount className="data-[state=inactive]:hidden">
          <HealthByLocation data={locationBuckets} />
        </TabsContent>
      )}
    </Tabs>
  )
}
