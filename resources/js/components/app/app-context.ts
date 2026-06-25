import { createContext, useContext } from 'react'

export interface AppContextValue {
  mobile: boolean
}

export const AppContext = createContext<AppContextValue>({ mobile: false })

export function useAppContext(): AppContextValue {
  return useContext(AppContext)
}
