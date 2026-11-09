import { createContext, use } from 'react'

export interface AppContextValue {
  mobile: boolean
}

export const AppContext = createContext<AppContextValue>({ mobile: false })

export function useAppContext(): AppContextValue {
  return use(AppContext)
}
