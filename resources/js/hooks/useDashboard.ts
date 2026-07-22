import { useQuery } from '@tanstack/react-query'
import { getDashboard } from '@/api/client'

export function useDashboard() {
  const query = useQuery({ queryKey: ['dashboard'], queryFn: getDashboard })
  return { data: query.data ?? null, loading: query.isPending, error: query.error }
}
