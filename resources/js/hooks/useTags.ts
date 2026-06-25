import { mockApi } from '@/api/mock'
import type { Tag } from '@/api/types'
import { useAsync } from './useAsync'

interface AsyncState<T> {
  data: T | null
  loading: boolean
  error: Error | null
}

export function useTags(): AsyncState<Tag[]> {
  return useAsync(() => mockApi.listTags(), [])
}
