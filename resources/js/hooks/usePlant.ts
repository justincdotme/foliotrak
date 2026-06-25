import { useQuery } from '@tanstack/react-query'
import { getPlant } from '@/api/client'

export function usePlant(id: number) {
  const query = useQuery({ queryKey: ['plant', id], queryFn: () => getPlant(id) })
  return { data: query.data ?? null, loading: query.isPending, error: query.error }
}
