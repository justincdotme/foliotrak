import { useQuery } from '@tanstack/react-query'
import { getTimeline } from '@/api/client'

export function useTimeline(plantId: number) {
  const query = useQuery({
    queryKey: ['timeline', plantId],
    queryFn: () => getTimeline(plantId),
  })
  return { data: query.data ?? null, loading: query.isPending, error: query.error }
}
