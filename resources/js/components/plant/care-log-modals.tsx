import type { CareType, Photo } from '@/api/types'
import { Modal } from '@/components/app/modal'
import { PhotoTile } from '@/components/app/photo-tile'
import { LogFertilizingForm } from '@/components/forms/log-fertilizing-form'
import { LogObservationForm } from '@/components/forms/log-observation-form'
import { LogRepottingForm } from '@/components/forms/log-repotting-form'
import { LogWateringForm } from '@/components/forms/log-watering-form'
import { RelocationEditForm } from '@/components/forms/relocation-edit-form'
import { fmtDateY } from '@/lib/format'
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
  photo: Photo | null
  setPhoto: (photo: Photo | null) => void
}

export function CareLogModals({ logFor, setLogFor, photo, setPhoto }: CareLogModalsProps) {
  const renderLogForm = (target: LogTarget) => {
    const close = () => setLogFor(null)
    switch (target.type) {
      case 'watering':
        return <LogWateringForm plantId={target.plantId} event={target.event} onDone={close} />
      case 'fertilizing':
        return (
          <LogFertilizingForm
            plantId={target.plantId}
            event={target.event}
            seedOccurredAt={target.seedOccurredAt}
            onDone={close}
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
          />
        )
      case 'observation':
        return <LogObservationForm plantId={target.plantId} event={target.event} onDone={close} />
      case 'relocation':
        return target.event ? (
          <RelocationEditForm plantId={target.plantId} event={target.event} onDone={close} />
        ) : null
    }
  }

  return (
    <>
      <Modal
        open={!!logFor}
        onClose={() => setLogFor(null)}
        title={logFor ? (logFor.event ? EDIT_TITLES[logFor.type] : LOG_TITLES[logFor.type]) : ''}
        wide={logFor?.type === 'observation' || logFor?.type === 'fertilizing'}
        dusk="log-modal"
      >
        {logFor && renderLogForm(logFor)}
      </Modal>

      <Modal
        open={!!photo}
        onClose={() => setPhoto(null)}
        title={photo?.caption || 'Photo'}
        subtitle={photo ? fmtDateY(photo.taken_on) : ''}
      >
        {photo && <PhotoTile photo={photo} className="aspect-[4/3] w-full" />}
        {photo?.original_filename && (
          <div className="tnum mt-2 text-[12px] text-text-subtle">{photo.original_filename}</div>
        )}
      </Modal>
    </>
  )
}
