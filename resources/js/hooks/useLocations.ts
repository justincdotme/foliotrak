import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { listLocations, createLocation } from '@/api/client'
import type { Location } from '@/api/types'

export function useLocations() {
  const query = useQuery({ queryKey: ['locations'], queryFn: listLocations })
  return { data: query.data ?? [], loading: query.isPending, error: query.error }
}

export function useCreateLocation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (name: string) => createLocation(name),
    onSuccess: (created: Location) => {
      qc.setQueryData<Location[]>(['locations'], old => [...(old ?? []), created])
    },
  })
}
