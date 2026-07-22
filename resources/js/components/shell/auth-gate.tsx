import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Navigate } from 'react-router-dom'
import { getUser } from '@/api/client'
import { Spinner } from '@/components/app/spinner'

// Gates the app behind the real Sanctum session and primes the ['auth-user']
// cache the shell and Settings read; bounces to login when no session exists.
export function AuthGate({ children }: { children: ReactNode }) {
  const { isPending, isError } = useQuery({
    queryKey: ['auth-user'],
    queryFn: getUser,
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
