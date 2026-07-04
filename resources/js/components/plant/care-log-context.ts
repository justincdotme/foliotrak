import { createContext, useContext } from 'react'
import type { CareEvent, CareType, Photo } from '@/api/types'

export interface LogTarget {
  plantId: number
  type: CareType
  event?: CareEvent
  seedOccurredAt?: string
}

export interface CareLogContextValue {
  openLog: (type: CareType, event?: CareEvent) => void
  viewPhoto: (photo: Photo) => void
}

export const CareLogContext = createContext<CareLogContextValue>({
  openLog: () => {},
  viewPhoto: () => {},
})

export function useCareLog(): CareLogContextValue {
  return useContext(CareLogContext)
}
