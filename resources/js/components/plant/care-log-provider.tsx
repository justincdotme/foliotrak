import { useMemo, useState, type ReactNode } from 'react'
import type { Photo } from '@/api/types'
import { CareLogContext, type CareLogContextValue, type LogTarget } from './care-log-context'
import { CareLogModals } from './care-log-modals'

export function CareLogProvider({ plantId, children }: { plantId: number; children: ReactNode }) {
  const [logFor, setLogFor] = useState<LogTarget | null>(null)
  const [photo, setPhoto] = useState<Photo | null>(null)

  const value = useMemo<CareLogContextValue>(
    () => ({
      openLog: (type, event) => setLogFor({ plantId, type, event }),
      viewPhoto: setPhoto,
    }),
    [plantId]
  )

  return (
    <CareLogContext.Provider value={value}>
      {children}
      <CareLogModals logFor={logFor} setLogFor={setLogFor} photo={photo} setPhoto={setPhoto} />
    </CareLogContext.Provider>
  )
}
