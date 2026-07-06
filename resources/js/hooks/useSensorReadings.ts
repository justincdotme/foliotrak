import { useQuery } from '@tanstack/react-query'
import { getPlantSensorReadings } from '@/api/client'

export function useSensorReadings(plantId: number, range: 'day' | 'week' | 'month') {
  return useQuery({
    // Own key root: nesting under ['plants'] would make every list invalidation
    // refetch readings, including for a plant that was just deleted.
    queryKey: ['sensor-readings', plantId, range],
    queryFn: () => getPlantSensorReadings(plantId, range),
    enabled: !!plantId,
  })
}
