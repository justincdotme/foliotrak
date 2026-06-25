import { mockApi } from '@/api/mock'
import type { GroupInsights } from '@/api/types'
import { useAsync } from './useAsync'

interface AsyncState<T> {
  data: T | null
  loading: boolean
  error: Error | null
}

export function useGroupInsights(tagId: number): AsyncState<GroupInsights> {
  return useAsync(() => mockApi.getGroupInsights(tagId), [tagId])
}
