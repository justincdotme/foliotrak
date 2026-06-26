import { useQuery } from '@tanstack/react-query'
import { getUser } from '@/api/client'

// Shares the ['auth-user'] cache the AuthGate primes, so the shell and Settings
// read the signed-in user without a second request.
export function useCurrentUser() {
  const query = useQuery({ queryKey: ['auth-user'], queryFn: getUser, retry: false })
  return { user: query.data ?? null, loading: query.isPending, error: query.error }
}
