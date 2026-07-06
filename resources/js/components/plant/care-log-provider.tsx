import { useMemo, useState, type ReactNode } from 'react'
import { photoUrl } from '@/lib/photos'
import { CareLogContext, type CareLogContextValue, type LogTarget } from './care-log-context'
import { CareLogModals } from './care-log-modals'

export function CareLogProvider({ plantId, children }: { plantId: number; children: ReactNode }) {
  const [logFor, setLogFor] = useState<LogTarget | null>(null)

  const value = useMemo<CareLogContextValue>(
    () => ({
      openLog: (type, event) => setLogFor({ plantId, type, event }),
      viewPhoto: photo => {
        if (photo.path) window.open(photoUrl(photo.path), '_blank')
      },
    }),
    [plantId]
  )

  return (
    <CareLogContext.Provider value={value}>
      {children}
      <CareLogModals logFor={logFor} setLogFor={setLogFor} />
    </CareLogContext.Provider>
  )
}
