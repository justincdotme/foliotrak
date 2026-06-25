import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Navigate } from 'react-router-dom'
import api from '@/lib/api'
import { Spinner } from '@/components/app/spinner'

// Gates the app behind the real Sanctum session. Pages render mock data during the
// design port; this only confirms a live session exists and bounces to login otherwise.
export function AuthGate({ children }: { children: ReactNode }) {
  const { isPending, isError } = useQuery({
    queryKey: ['auth-user'],
    queryFn: async () => {
      const response = await api.get('/api/user')
      return response.data
    },
    retry: false,
  })

  if (isPending) {
    return (
      <div className="grid h-full place-items-center bg-bg">
        <Spinner />
      </div>
    )
  }

  if (isError) {
    return <Navigate to="/login" replace />
  }

  return <>{children}</>
}
