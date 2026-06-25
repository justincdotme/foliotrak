import { mockApi } from '@/api/mock'
import type { PlantWithTags } from '@/api/types'
import { useAsync } from './useAsync'

interface AsyncState<T> {
  data: T | null
  loading: boolean
  error: Error | null
}

export function usePlants(): AsyncState<PlantWithTags[]> {
  return useAsync(() => mockApi.listPlants(), [])
}
