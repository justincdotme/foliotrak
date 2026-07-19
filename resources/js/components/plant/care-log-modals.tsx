import { useEffect, useRef, useState } from 'react'
import type { CareType } from '@/api/types'
import { Modal } from '@/components/app/modal'
import { Button } from '@/components/ui/button'
import { LogFertilizingForm } from '@/components/forms/log-fertilizing-form'
import { LogObservationForm } from '@/components/forms/log-observation-form'
import { LogRepottingForm } from '@/components/forms/log-repotting-form'
import { LogWateringForm } from '@/components/forms/log-watering-form'
import { RelocationEditForm } from '@/components/forms/relocation-edit-form'
import type { LogTarget } from './care-log-context'

const LOG_TITLES: Record<CareType, string> = {
  watering: 'Log watering',
  fertilizing: 'Log fertilizing',
  repotting: 'Log repotting',
  observation: 'Log observation',
  relocation: 'Log relocation',
  equipment: 'Equipment',
}

const EDIT_TITLES: Record<CareType, string> = {
  watering: 'Edit watering',
  fertilizing: 'Edit fertilizing',
  repotting: 'Edit repotting',
  observation: 'Edit observation',
  relocation: 'Edit move',
  equipment: 'Equipment',
}

interface CareLogModalsProps {
  logFor: LogTarget | null
  setLogFor: (target: LogTarget | null) => void
}

export function CareLogModals({ logFor, setLogFor }: CareLogModalsProps) {
  const dirtyRef = useRef(false)
  const [confirmingClose, setConfirmingClose] = useState(false)

  useEffect(() => {
    dirtyRef.current = false
    setConfirmingClose(false)
  }, [logFor])

  const requestClose = () => {
    if (dirtyRef.current) {
      setConfirmingClose(true)
      return
    }
    setLogFor(null)
  }

  const renderLogForm = (target: LogTarget) => {
    const close = () => setLogFor(null)
    switch (target.type) {
      case 'watering':
        return (
          <LogWateringForm
            plantId={target.plantId}
            event={target.event}
            onDone={close}
            dirtyRef={dirtyRef}
          />
        )
      case 'fertilizing':
        return (
          <LogFertilizingForm
            plantId={target.plantId}
            event={target.event}
            seedOccurredAt={target.seedOccurredAt}
            onDone={close}
            dirtyRef={dirtyRef}
          />
        )
      case 'repotting':
        return (
          <LogRepottingForm
            plantId={target.plantId}
            event={target.event}
            onDone={close}
            onLogFertilizer={iso =>
              setLogFor({ plantId: target.plantId, type: 'fertilizing', seedOccurredAt: iso })
            }
            dirtyRef={dirtyRef}
          />
        )
      case 'observation':
        return (
          <LogObservationForm
            plantId={target.plantId}
            event={target.event}
            onDone={close}
            dirtyRef={dirtyRef}
          />
        )
      case 'relocation':
        return target.event ? (
          <RelocationEditForm
            plantId={target.plantId}
            event={target.event}
            onDone={close}
            dirtyRef={dirtyRef}
          />
        ) : null
    }
  }

  return (
    <Modal
      open={!!logFor}
      onClose={requestClose}
      title={logFor ? (logFor.event ? EDIT_TITLES[logFor.type] : LOG_TITLES[logFor.type]) : ''}
      wide={logFor?.type === 'observation' || logFor?.type === 'fertilizing'}
      dusk="log-modal"
      footer={
        confirmingClose ? (
          <div className="-m-4 flex w-[calc(100%+2rem)] items-center gap-2 rounded-b-card bg-overdue/10 p-4">
            <span className="mr-auto text-[13px] font-medium text-overdue">
              You have unsaved changes.
            </span>
            <Button variant="ghost" size="sm" onClick={() => setConfirmingClose(false)}>
              Keep editing
            </Button>
            <Button variant="danger" size="sm" onClick={() => setLogFor(null)}>
              Discard
            </Button>
          </div>
        ) : undefined
      }
    >
      {logFor && renderLogForm(logFor)}
    </Modal>
  )
}
