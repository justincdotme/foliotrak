import { useQuery } from '@tanstack/react-query'
import { listPhotos } from '@/api/client'

export function usePlantPhotos(plantId: number) {
  const query = useQuery({
    queryKey: ['plant', plantId, 'photos'],
    queryFn: () => listPhotos(plantId),
  })
  return { data: query.data ?? null, loading: query.isPending, error: query.error }
}
