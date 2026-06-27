import { useQuery } from '@tanstack/react-query'
import { listEquipment } from '@/api/client'

export function useEquipment() {
  const query = useQuery({ queryKey: ['equipment'], queryFn: listEquipment })
  return { data: query.data ?? [], loading: query.isPending }
}
