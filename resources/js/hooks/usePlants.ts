import { useQuery, keepPreviousData } from '@tanstack/react-query'
import { listPlants } from '@/api/client'
import type { PlantSort, SortDirection } from '@/api/types'

type PlantListParams = { sort?: PlantSort; direction?: SortDirection }

export function usePlants(params: PlantListParams = {}) {
  const query = useQuery({
    queryKey: ['plants', params],
    queryFn: () => listPlants(params),
    placeholderData: keepPreviousData,
  })
  return { data: query.data ?? null, loading: query.isPending, error: query.error }
}
