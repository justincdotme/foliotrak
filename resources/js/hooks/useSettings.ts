import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { SettingsPayload } from '@/api/client'
import { getSettings, updateSettings } from '@/api/client'

export function useSettings() {
  const query = useQuery({ queryKey: ['settings'], queryFn: getSettings })
  return { data: query.data ?? null, loading: query.isPending, error: query.error }
}

export function useUpdateSettings() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: SettingsPayload) => updateSettings(payload),
    // The PATCH returns the saved settings, so seed the cache directly. The key
    // is not shown anywhere else, so invalidating ['auth-user'] (and risking an
    // AuthGate refetch that bounces to login) buys nothing.
    onSuccess: settings => queryClient.setQueryData(['settings'], settings),
  })
}
