import { useMemo } from 'react'
import type { CareEvent, GrowthTrendPoint, PlantTimeline, TrendPoint } from '@/api/types'

export interface PlantChartData {
  events: CareEvent[]
  healthTrend: TrendPoint[]
  weightTrend: TrendPoint[]
  growthTrend: GrowthTrendPoint[]
  lightTrend: TrendPoint[]
  leafSizeTrend: TrendPoint[]
  hasHealth: boolean
  hasWeight: boolean
  hasGrowth: boolean
  hasLight: boolean
  hasLeafSize: boolean
}

// Each observation-derived series is charted only when it holds a real value; overall_health and
// the rest are optional, so a length check alone would draw an empty line.
export function usePlantChartData(timeline: PlantTimeline | null): PlantChartData {
  return useMemo(() => {
    const healthTrend = timeline?.health_trend ?? []
    const weightTrend = timeline?.weight_trend ?? []
    const growthTrend = timeline?.growth_trend ?? []
    const lightTrend = timeline?.light_trend ?? []
    const leafSizeTrend = timeline?.leaf_size_trend ?? []
    return {
      events: timeline?.events ?? [],
      healthTrend,
      weightTrend,
      growthTrend,
      lightTrend,
      leafSizeTrend,
      hasHealth: healthTrend.some(p => p.value != null),
      hasWeight: weightTrend.some(p => p.value != null),
      hasGrowth: growthTrend.some(p => p.value != null),
      hasLight: lightTrend.some(p => p.value != null),
      hasLeafSize: leafSizeTrend.some(p => p.value != null),
    }
  }, [timeline])
}
