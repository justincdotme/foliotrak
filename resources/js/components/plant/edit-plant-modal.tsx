import { Check, Info, MapPin, Move } from 'lucide-react'
import { useEffect, useState } from 'react'
import type { PlantWithTags, Tag } from '@/api/types'
import { mockApi } from '@/api/mock'
import { Button } from '@/components/ui/button'
import { Chip } from '@/components/app/chip'
import { Field } from '@/components/app/field'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/app/modal'
import { Segmented } from '@/components/app/segmented'
import { Textarea } from '@/components/ui/textarea'
import { Toggle } from '@/components/app/toggle'
import { commit } from '@/hooks/useAsync'
import { TAGS } from '@/api/mock'

interface EditPlantModalProps {
  plant: PlantWithTags
  open: boolean
  onClose: () => void
}

const STATUS_HELP: Record<string, string> = {
  active: 'Active and on its care schedule.',
  archived: 'Kept for history; reminders paused.',
  dead: 'Kept for historical data; reminders stop. Use this if the plant has died.',
}

export function EditPlantModal({ plant, open, onClose }: EditPlantModalProps) {
  const [location, setLocation] = useState(plant.location || '')
  const [notes, setNotes] = useState(plant.notes || '')
  const [status, setStatus] = useState(plant.status)
  const [tags, setTags] = useState<Tag[]>(plant.tags)
  const [logMove, setLogMove] = useState(true)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (open) {
      setLocation(plant.location || '')
      setNotes(plant.notes || '')
      setStatus(plant.status)
      setTags(plant.tags)
      setLogMove(true)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, plant.id])

  const toggleTag = (t: Tag) =>
    setTags(ts => (ts.find(x => x.id === t.id) ? ts.filter(x => x.id !== t.id) : [...ts, t]))

  const moved = location.trim() !== (plant.location || '')

  const save = async () => {
    setBusy(true)
    await mockApi.updatePlant(plant.id, {
      location: location.trim() || null,
      notes: notes.trim() || null,
      status,
      tags,
    })
    if (moved && logMove && location.trim()) {
      await mockApi.createRelocation(plant.id, {
        from_location: plant.location,
        to_location: location.trim(),
      })
    }
    commit()
    setBusy(false)
    onClose()
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Edit plant"
      subtitle={plant.common_name || undefined}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button onClick={save} disabled={busy}>
            <Check size={16} />
            Save changes
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <Field label="Location" hint="where it lives now">
          <div className="relative">
            <MapPin
              size={16}
              className="absolute left-3 top-1/2 -translate-y-1/2 text-text-subtle"
            />
            <Input
              value={location}
              onChange={e => setLocation(e.target.value)}
              className="pl-9"
              placeholder="Living room shelf"
            />
          </div>
        </Field>
        {moved && (
          // eslint-disable-next-line jsx-a11y/label-has-associated-control
          <label className="flex items-center gap-2.5 -mt-1 rounded-[8px] bg-surface-raised border border-border p-2.5">
            <Toggle checked={logMove} onChange={setLogMove} />
            <span className="text-[12px] text-text-muted">
              <Move size={12} className="inline mr-1" />
              Add this move to the placement history
            </span>
          </label>
        )}
        <Field label="Notes">
          <Textarea
            value={notes}
            onChange={e => setNotes(e.target.value)}
            placeholder="Anything worth remembering about this plant"
          />
        </Field>
        <Field label="Tags">
          <div className="flex flex-wrap gap-1.5">
            {TAGS.map(t => {
              const sel = !!tags.find(x => x.id === t.id)
              return (
                <Chip
                  key={t.id}
                  color={t.color || undefined}
                  active={sel}
                  outline={!sel}
                  onClick={() => toggleTag(t)}
                >
                  {sel && <Check size={12} />}
                  {t.name}
                </Chip>
              )
            })}
          </div>
        </Field>
        <Field label="Status">
          <Segmented
            value={status}
            onChange={v => setStatus(v as 'active' | 'archived' | 'dead')}
            options={[
              { value: 'active', label: 'Active' },
              { value: 'archived', label: 'Archived' },
              { value: 'dead', label: 'Dead' },
            ]}
          />
          <div className="mt-1.5 text-[12px] text-text-subtle flex items-start gap-1.5">
            <Info size={13} className="mt-px shrink-0" />
            {STATUS_HELP[status]}
          </div>
        </Field>
      </div>
    </Modal>
  )
}
