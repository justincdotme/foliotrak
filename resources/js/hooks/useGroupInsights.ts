import { useQuery } from '@tanstack/react-query'
import { getGroupInsights } from '@/api/client'

export function useGroupInsights(tagId: number | null) {
  const query = useQuery({
    queryKey: ['insights', 'group', tagId],
    queryFn: () => getGroupInsights(tagId as number),
    enabled: tagId != null,
  })
  return {
    data: query.data ?? null,
    loading: tagId == null || query.isPending,
    error: query.error,
  }
}
