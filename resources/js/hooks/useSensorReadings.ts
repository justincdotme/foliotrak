import { useQuery } from '@tanstack/react-query'
import { getPlantSensorReadings } from '@/api/client'

export function useSensorReadings(plantId: number, range: 'day' | 'week' | 'month') {
  return useQuery({
    queryKey: ['plants', plantId, 'sensor-readings', range],
    queryFn: () => getPlantSensorReadings(plantId, range),
    enabled: !!plantId,
  })
}
