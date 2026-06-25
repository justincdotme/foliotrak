import { mockApi } from '@/api/mock'
import type { DashboardData } from '@/api/types'
import { useAsync } from './useAsync'

interface AsyncState<T> {
  data: T | null
  loading: boolean
  error: Error | null
}

export function useDashboard(): AsyncState<DashboardData> {
  return useAsync(() => mockApi.getDashboard(), [])
}
