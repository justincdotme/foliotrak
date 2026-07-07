import { BarChart3 } from 'lucide-react'
import type { PlantRecommendations, PlantTimeline } from '@/api/types'
import { Card } from '@/components/ui/card'
import { SectionTitle } from '@/components/app/section-title'
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
  plantId: number
  timeline: PlantTimeline | null
  recommendations: PlantRecommendations | null
}

export function PlantChartsPanel({ plantId, timeline, recommendations }: PlantChartsPanelProps) {
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
  const hasTrends = hasHealth || hasWeight || hasGrowth || hasLeafSize

  return (
    <Card className="p-4" dusk="charts-panel">
      <SectionTitle icon={BarChart3}>Charts</SectionTitle>
      <Tabs defaultValue="trends">
        <TabsList className="overflow-x-auto w-full justify-start">
          <TabsTrigger value="trends" dusk="chart-tab-trends">
            Trends
          </TabsTrigger>
          <TabsTrigger value="activity" dusk="chart-tab-activity">
            Activity
          </TabsTrigger>
          <TabsTrigger value="light" dusk="chart-tab-light">
            Light
          </TabsTrigger>
          <TabsTrigger value="environment" dusk="chart-tab-environment">
            Environment
          </TabsTrigger>
          {showLocationTab && (
            <TabsTrigger value="location" dusk="chart-tab-location">
              Location
            </TabsTrigger>
          )}
        </TabsList>

        <TabsContent value="trends" forceMount className="data-[state=inactive]:hidden">
          <div className="space-y-4">
            {hasHealth && <TimelineOverlay health={healthTrend} events={events} />}
            {hasTrends ? (
              <div
                className="grid gap-4"
                style={{ gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))' }}
              >
                {hasHealth && <HealthTrend data={healthTrend} />}
                {hasWeight && <WeightTrend data={weightTrend} />}
                {hasGrowth && <GrowthTrend data={growthTrend} />}
                {hasLeafSize && <LeafSizeTrend data={leafSizeTrend} />}
              </div>
            ) : (
              <EmptyState icon={BarChart3} title="No trend data yet">
                Record a health rating, weight, or a plant measurement in an observation to see
                trends here.
              </EmptyState>
            )}
          </div>
        </TabsContent>

        <TabsContent value="activity" forceMount className="data-[state=inactive]:hidden">
          <div className="space-y-4">
            {events.length > 0 ? (
              <ActivityHeatmap events={events} />
            ) : (
              <EmptyState icon={BarChart3} title="No activity yet">
                Log a watering, feeding, or observation to see it on the calendar.
              </EmptyState>
            )}
          </div>
        </TabsContent>

        <TabsContent value="light" forceMount className="data-[state=inactive]:hidden">
          <div className="space-y-4">
            {hasLight ? (
              <LightTrend data={lightTrend} />
            ) : (
              <EmptyState icon={BarChart3} title="No light data">
                Record a light level in an observation to see the trend here.
              </EmptyState>
            )}
          </div>
        </TabsContent>

        <TabsContent value="environment" forceMount className="data-[state=inactive]:hidden">
          <EnvironmentChart plantId={plantId} />
        </TabsContent>

        {showLocationTab && (
          <TabsContent value="location" forceMount className="data-[state=inactive]:hidden">
            <HealthByLocation data={locationBuckets} />
          </TabsContent>
        )}
      </Tabs>
    </Card>
  )
}
