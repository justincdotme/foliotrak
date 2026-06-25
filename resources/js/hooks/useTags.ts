import { useQuery } from '@tanstack/react-query'
import { listTags } from '@/api/client'

export function useTags() {
  const query = useQuery({ queryKey: ['tags'], queryFn: listTags })
  return { data: query.data ?? null, loading: query.isPending, error: query.error }
}
