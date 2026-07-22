import { useQuery } from '@tanstack/react-query'
import { getGroupInsights } from '@/api/client'

type GroupInsightsParams = { tag?: number; location?: number }

export function useGroupInsights(params: GroupInsightsParams) {
  const query = useQuery({
    queryKey: ['insights', 'group', params],
    queryFn: () => getGroupInsights(params),
  })
  return {
    data: query.data ?? null,
    loading: query.isPending,
    error: query.error,
  }
}
