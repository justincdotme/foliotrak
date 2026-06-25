import { mockApi } from '@/api/mock'
import type { PlantTimeline } from '@/api/types'
import { useAsync } from './useAsync'

interface AsyncState<T> {
  data: T | null
  loading: boolean
  error: Error | null
}

export function useTimeline(id: number): AsyncState<PlantTimeline | null> {
  return useAsync(() => mockApi.getTimeline(id), [id])
}
