import { useEffect, useState } from 'react'
import type { Sensor } from '@/api/types'
import { useSensorCalibration, useUpdateSensorCalibration } from '@/hooks/useSensors'
import { extractValidationError } from '@/lib/handle-api-error'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/app/modal'
import { Spinner } from '@/components/app/spinner'

interface SensorCalibrationModalProps {
  sensor: Sensor
  onClose: () => void
}

// Maps slider anchors (1-10) to raw ADC values so observation auto-fill can
// interpolate the probe's uncalibrated readings onto the soil moisture scale.
export function SensorCalibrationModal({ sensor, onClose }: SensorCalibrationModalProps) {
  const calibration = useSensorCalibration(sensor.id)
  const save = useUpdateSensorCalibration()

  const [anchors, setAnchors] = useState<Map<number, number>>(new Map())
  const [position, setPosition] = useState(5)
  const [draft, setDraft] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [savedFlash, setSavedFlash] = useState(false)
  const [confirmingClose, setConfirmingClose] = useState(false)

  const data = calibration.data

  // Anchors always mirror server state (saved points, else the suggested
  // defaults derived from reading history: driest = 1, wettest = 10). The
  // only local edit is `draft`; a successful save flows back through the
  // query cache and re-seeds both. Seeding pauses while a save is in flight
  // so a response landing mid-edit cannot revert a newer anchor.
  useEffect(() => {
    if (!data || save.isPending) return
    const source = data.points.length > 0 ? data.points : (data.suggested ?? [])
    const next = new Map(source.map(p => [p.position, p.value]))
    setAnchors(next)
    setDraft(next.has(position) ? String(next.get(position)) : '')
  }, [data, position, save.isPending])

  const storedDraft = anchors.has(position) ? String(anchors.get(position)) : ''
  const dirty = draft !== storedDraft

  const persist = async (next: Map<number, number>): Promise<boolean> => {
    const points = [...next.entries()]
      .sort((a, b) => a[0] - b[0])
      .map(([anchorPosition, value]) => ({ position: anchorPosition, value }))
    try {
      await save.mutateAsync({ id: sensor.id, points })
      setError(null)
      setSavedFlash(true)
      return true
    } catch (err) {
      setError(extractValidationError(err, ['points'], 'Could not save calibration.'))
      return false
    }
  }

  // An emptied field is a removal: the anchor at the current position drops
  // from the set, mirroring how a typed value upserts it.
  const commit = async (): Promise<boolean> => {
    if (save.isPending) {
      return false
    }
    const next = new Map(anchors)
    if (draft === '') {
      if (!next.has(position)) {
        return true
      }
      next.delete(position)
      return persist(next)
    }
    const parsed = Number(draft)
    if (!Number.isInteger(parsed) || parsed < 0 || parsed > 4095) {
      setError('Enter a whole number between 0 and 4095.')
      return false
    }
    next.set(position, parsed)
    return persist(next)
  }

  const removeAnchor = () => {
    if (save.isPending || !anchors.has(position)) {
      return
    }
    const next = new Map(anchors)
    next.delete(position)
    void persist(next)
  }

  const requestClose = () => {
    if (dirty) {
      setConfirmingClose(true)
      return
    }
    onClose()
  }

  return (
    <Modal
      open
      onClose={requestClose}
      title={`Calibrate ${sensor.name}`}
      subtitle="Set the raw reading at a few anchor positions on the 1-10 scale; positions between anchors are filled in automatically. Values save when you leave the field."
      dusk="calibration-modal"
      footer={
        confirmingClose ? (
          <>
            <span className="mr-auto self-center text-[12px] text-text-muted">
              Apply the unsaved calibration change?
            </span>
            <Button
              variant="ghost"
              dusk="calibration-discard"
              onMouseDown={e => e.preventDefault()}
              onClick={onClose}
            >
              Discard
            </Button>
            <Button
              dusk="calibration-save-close"
              onMouseDown={e => e.preventDefault()}
              onClick={async () => {
                if (await commit()) onClose()
                else setConfirmingClose(false)
              }}
            >
              Save
            </Button>
          </>
        ) : (
          <Button variant="ghost" onClick={requestClose}>
            Close
          </Button>
        )
      }
    >
      {calibration.isPending ? (
        <Spinner />
      ) : (
        <div className="space-y-3">
          {data?.latest && (
            <div className="text-[12px] text-text-muted">
              Latest reading: <span className="tnum text-text">{data.latest.value}</span>
            </div>
          )}
          <div className="flex items-center gap-3">
            <span className="text-[12px] text-text-subtle">1</span>
            <input
              type="range"
              min={1}
              max={10}
              step={1}
              value={position}
              onChange={e => {
                setConfirmingClose(false)
                setPosition(Number(e.target.value))
              }}
              className="flex-1"
              aria-label="Calibration position 1 to 10"
              dusk="calibration-position"
            />
            <span className="text-[12px] text-text-subtle">10</span>
            <span className="tnum text-[13px] text-text min-w-[2ch] text-right">{position}</span>
          </div>
          <div className="flex items-center gap-2">
            <Input
              type="number"
              min="0"
              max="4095"
              value={draft}
              onChange={e => {
                setDraft(e.target.value)
                setError(null)
                setSavedFlash(false)
                setConfirmingClose(false)
              }}
              onBlur={() => {
                if (dirty) commit()
              }}
              placeholder="Raw value at this position"
              aria-label={`Raw value at position ${position}`}
              className="h-8 text-[13px]"
              dusk="calibration-value"
              disabled={save.isPending}
            />
            {anchors.has(position) && (
              <Button
                variant="ghost"
                size="sm"
                dusk="calibration-remove"
                disabled={save.isPending}
                onMouseDown={e => e.preventDefault()}
                onClick={removeAnchor}
              >
                Remove
              </Button>
            )}
            {savedFlash && <span className="text-[11px] text-ok">Saved</span>}
          </div>
          {anchors.size > 0 ? (
            <div className="text-[11px] text-text-muted">
              {(data?.points.length ?? 0) > 0 ? 'Anchors: ' : 'Defaults (full sensor range): '}
              {[...anchors.entries()]
                .sort((a, b) => a[0] - b[0])
                .map(([anchorPosition, value]) => `${anchorPosition}: ${value}`)
                .join(' · ')}
            </div>
          ) : (
            <div className="text-[11px] text-text-muted">
              No anchors yet. Save a raw value at two or more positions (dry end and wet end) to
              enable auto-fill.
            </div>
          )}
          {error && <div className="text-[11px] text-overdue">{error}</div>}
        </div>
      )}
    </Modal>
  )
}
