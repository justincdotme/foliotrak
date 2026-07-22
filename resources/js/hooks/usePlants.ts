import { useQuery } from '@tanstack/react-query'
import { listPlants } from '@/api/client'

export function usePlants() {
  const query = useQuery({ queryKey: ['plants'], queryFn: listPlants })
  return { data: query.data ?? null, loading: query.isPending, error: query.error }
}
