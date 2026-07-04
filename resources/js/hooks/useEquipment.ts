import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { listEquipment, createEquipment, updateEquipment, deleteEquipment } from '@/api/client'
import type { EquipmentOption } from '@/api/types'

export function useEquipment() {
  const query = useQuery({ queryKey: ['equipment'], queryFn: listEquipment })
  return { data: query.data ?? [], loading: query.isPending }
}

export function useCreateEquipment() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (label: string) => createEquipment(label),
    onSuccess: (created: EquipmentOption) => {
      qc.setQueryData<EquipmentOption[]>(['equipment'], old => [...(old ?? []), created])
    },
  })
}

export function useUpdateEquipment() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: { label?: string } }) =>
      updateEquipment(id, payload),
    onSuccess: (updated: EquipmentOption) => {
      qc.setQueryData<EquipmentOption[]>(['equipment'], old =>
        (old ?? []).map(e => (e.id === updated.id ? updated : e))
      )
      qc.invalidateQueries({ queryKey: ['plants'] })
      qc.invalidateQueries({ queryKey: ['plant'] })
    },
  })
}

export function useDeleteEquipment() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteEquipment(id),
    onSuccess: (_data, id) => {
      qc.setQueryData<EquipmentOption[]>(['equipment'], old => (old ?? []).filter(e => e.id !== id))
      qc.invalidateQueries({ queryKey: ['plants'] })
      qc.invalidateQueries({ queryKey: ['plant'] })
    },
  })
}
