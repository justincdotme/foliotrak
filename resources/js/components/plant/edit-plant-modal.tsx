import { Info } from 'lucide-react'
import { Check } from 'lucide-react'
import { useEffect, useState } from 'react'
import type { PlantWithTags, Tag } from '@/api/types'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/app/field'
import { LocationCombobox } from '@/components/app/location-combobox'
import { Modal } from '@/components/app/modal'
import { Segmented } from '@/components/app/segmented'
import { TagInlineCreate } from '@/components/app/tag-inline-create'
import { Textarea } from '@/components/ui/textarea'
import { useTags } from '@/hooks/useTags'
import { useUpdatePlant } from '@/hooks/usePlantMutations'

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
  const { data: allTags } = useTags()
  const update = useUpdatePlant(plant.id)
  const [locationId, setLocationId] = useState<number | null>(plant.location?.id ?? null)
  const [notes, setNotes] = useState(plant.notes || '')
  const [status, setStatus] = useState(plant.status)
  const [tags, setTags] = useState<Tag[]>(plant.tags)

  useEffect(() => {
    if (open) {
      setLocationId(plant.location?.id ?? null)
      setNotes(plant.notes || '')
      setStatus(plant.status)
      setTags(plant.tags)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, plant.id])

  const toggleTag = (t: Tag) =>
    setTags(ts => (ts.find(x => x.id === t.id) ? ts.filter(x => x.id !== t.id) : [...ts, t]))

  const save = async () => {
    await update.mutateAsync({
      location_id: locationId,
      notes: notes.trim() || null,
      status,
      tag_ids: tags.map(t => t.id),
    })
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
          <Button onClick={save} disabled={update.isPending}>
            <Check size={16} />
            Save changes
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <Field label="Location" hint="where it lives now">
          <LocationCombobox value={locationId} onChange={setLocationId} />
        </Field>
        <Field label="Notes">
          <Textarea
            value={notes}
            onChange={e => setNotes(e.target.value)}
            placeholder="Anything worth remembering about this plant"
          />
        </Field>
        <Field label="Tags">
          <div className="flex flex-wrap gap-1.5">
            <TagInlineCreate allTags={allTags || []} selectedTags={tags} onToggle={toggleTag} />
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
