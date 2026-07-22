import { useQuery } from '@tanstack/react-query'
import { getRecommendations } from '@/api/client'

export function useRecommendations(plantId: number) {
  const query = useQuery({
    queryKey: ['recommendations', plantId],
    queryFn: () => getRecommendations(plantId),
  })
  return { data: query.data ?? null, loading: query.isPending, error: query.error }
}
