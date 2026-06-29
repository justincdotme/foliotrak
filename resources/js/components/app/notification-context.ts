import { createContext, useContext } from 'react'

export interface NotificationContextValue {
  error: string | null
  showError: (message: string) => void
  clearError: () => void
}

export const NotificationContext = createContext<NotificationContextValue>({
  error: null,
  showError: () => {},
  clearError: () => {},
})

export function useNotification(): NotificationContextValue {
  return useContext(NotificationContext)
}
