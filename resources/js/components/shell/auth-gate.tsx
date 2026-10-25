import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Navigate } from 'react-router-dom'
import { getUser } from '@/api/client'
import { Spinner } from '@/components/app/spinner'
import { Button } from '@/components/ui/button'
import { SessionExpiredError } from '@/lib/errors'

// Gates the app behind the real Sanctum session and primes the ['auth-user']
// cache the shell and Settings read. Only a 401 means the session is gone; the
// same request also fails while a laptop reconnects after sleep or the server
// restarts, and sending the user to login then discards a session and remember
// cookie that are still valid.
export function AuthGate({ children }: { children: ReactNode }) {
  const { data, error, isPending, isFetching, refetch } = useQuery({
    queryKey: ['auth-user'],
    queryFn: getUser,
    retry: false,
  })

  if (error instanceof SessionExpiredError) {
    return <Navigate to="/login" replace />
  }

  if (data) {
    return <>{children}</>
  }

  if (isPending) {
    return (
      <div className="grid h-full place-items-center bg-bg">
        <Spinner />
      </div>
    )
  }

  return (
    <div className="grid h-full place-items-center bg-bg">
      <div className="flex flex-col items-center gap-3 text-center">
        <p className="text-[13px] text-text-muted">Could not reach Foliotrak.</p>
        <Button variant="outline" size="sm" disabled={isFetching} onClick={() => void refetch()}>
          Retry
        </Button>
      </div>
    </div>
  )
}
