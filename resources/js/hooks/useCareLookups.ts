import { useQuery } from '@tanstack/react-query'
import { listFertilizerForms, listNutrients, listSymptoms } from '@/api/client'

// Seeded vocabularies change only on a deploy, so cache them aggressively.
const REFERENCE_STALE = 1000 * 60 * 60

export function useSymptoms() {
  const query = useQuery({
    queryKey: ['symptoms'],
    queryFn: listSymptoms,
    staleTime: REFERENCE_STALE,
  })
  return { data: query.data ?? [], loading: query.isPending, error: query.error }
}

export function useNutrients() {
  const query = useQuery({
    queryKey: ['nutrients'],
    queryFn: listNutrients,
    staleTime: REFERENCE_STALE,
  })
  return { data: query.data ?? [], loading: query.isPending, error: query.error }
}

export function useFertilizerForms() {
  const query = useQuery({
    queryKey: ['fertilizerForms'],
    queryFn: listFertilizerForms,
    staleTime: REFERENCE_STALE,
  })
  return { data: query.data ?? [], loading: query.isPending, error: query.error }
}
