import { useCallback, useEffect, useState, type ReactNode } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { NotificationContext } from './notification-context'

export function NotificationProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const [error, setError] = useState<string | null>(null)
  const showError = useCallback((message: string) => setError(message), [])
  const clearError = useCallback(() => setError(null), [])

  useEffect(() => {
    const unsubscribe = queryClient.getMutationCache().subscribe(event => {
      if (event.type === 'updated' && event.mutation.state.status === 'success') {
        setError(null)
      }
    })
    return unsubscribe
  }, [queryClient])

  return (
    <NotificationContext.Provider value={{ error, showError, clearError }}>
      {children}
    </NotificationContext.Provider>
  )
}
