import { AlertTriangle, Info } from 'lucide-react'
import { Check } from 'lucide-react'
import { useEffect, useState } from 'react'
import type { PlantSensor, PlantWithTags, Tag } from '@/api/types'
import { TooltipButton } from '@/components/ui/tooltip-button'
import { Field } from '@/components/app/field'
import { Input } from '@/components/ui/input'
import { LocationCombobox } from '@/components/app/location-combobox'
import { Modal } from '@/components/app/modal'
import { Segmented } from '@/components/app/segmented'
import { SensorSelect } from '@/components/app/sensor-select'
import { TagInlineCreate } from '@/components/app/tag-inline-create'
import { Textarea } from '@/components/ui/textarea'
import { useSensors } from '@/hooks/useSensors'
import { useTags } from '@/hooks/useTags'
import { useUpdatePlant } from '@/hooks/usePlantMutations'
import { handleApiError } from '@/lib/handle-api-error'
import { Button } from '@/components/ui/button'

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
  const { data: allSensors } = useSensors()
  const update = useUpdatePlant(plant.id)
  const [locationId, setLocationId] = useState<number | null>(plant.location?.id ?? null)
  const [notes, setNotes] = useState(plant.notes || '')
  const [status, setStatus] = useState(plant.status)
  const [tags, setTags] = useState<Tag[]>(plant.tags)
  const [sensors, setSensors] = useState<PlantSensor[]>(plant.sensors ?? [])
  const [nickname, setNickname] = useState(plant.nickname || '')
  const [formError, setFormError] = useState<string | null>(null)

  useEffect(() => {
    if (open) {
      setLocationId(plant.location?.id ?? null)
      setNotes(plant.notes || '')
      setStatus(plant.status)
      setTags(plant.tags)
      setSensors(plant.sensors ?? [])
      setNickname(plant.nickname || '')
      setFormError(null)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, plant.id])

  const toggleTag = (t: Tag) =>
    setTags(ts => (ts.find(x => x.id === t.id) ? ts.filter(x => x.id !== t.id) : [...ts, t]))

  const toggleSensor = (s: PlantSensor) =>
    setSensors(ss => (ss.find(x => x.id === s.id) ? ss.filter(x => x.id !== s.id) : [...ss, s]))

  const save = async () => {
    setFormError(null)
    try {
      await update.mutateAsync({
        nickname: nickname.trim() || null,
        location_id: locationId,
        notes: notes.trim() || null,
        status,
        tag_ids: tags.map(t => t.id),
        sensor_ids: sensors.map(s => s.id),
      })
      onClose()
    } catch (err) {
      setFormError(handleApiError(err))
    }
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
          <TooltipButton
            onClick={save}
            disabled={update.isPending}
            tooltipContent={update.isPending ? 'Saving...' : undefined}
          >
            <Check size={16} />
            Save changes
          </TooltipButton>
        </>
      }
    >
      <div className="space-y-4">
        <Field label="Nickname" hint="optional">
          <Input
            value={nickname}
            onChange={e => setNickname(e.target.value)}
            placeholder="Kitchen Pothos, Big Fern…"
          />
        </Field>
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
        <Field label="Sensors">
          <div className="flex flex-wrap gap-1.5">
            <SensorSelect
              allSensors={allSensors || []}
              selectedSensors={sensors}
              onToggle={toggleSensor}
            />
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
        {formError && (
          <div className="flex items-center gap-1.5 text-[12px] text-overdue">
            <AlertTriangle size={14} />
            {formError}
          </div>
        )}
      </div>
    </Modal>
  )
}
