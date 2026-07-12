import { useEffect, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  AlertTriangle,
  Bell,
  Check,
  LogOut,
  Pencil,
  Plus,
  Radio,
  Settings,
  Settings2,
  Sun,
  Tags,
  Trash2,
  User as UserIcon,
  X,
} from 'lucide-react'
import type { ThemeChoice } from '@/hooks/useTheme'
import type { DiscoveredSensor, EquipmentOption, Sensor, Tag } from '@/api/types'
import { useSettings, useUpdateSettings } from '@/hooks/useSettings'
import { useCurrentUser } from '@/hooks/useCurrentUser'
import { useTags, useCreateTag, useUpdateTag, useDeleteTag } from '@/hooks/useTags'
import {
  useEquipment,
  useCreateEquipment,
  useUpdateEquipment,
  useDeleteEquipment,
} from '@/hooks/useEquipment'
import {
  useSensors,
  useDiscoverSensors,
  useTestConnection,
  useCreateSensor,
  useSensorTypes,
  useUpdateSensor,
  useDeleteSensor,
} from '@/hooks/useSensors'
import { Button } from '@/components/ui/button'
import { TooltipButton } from '@/components/ui/tooltip-button'
import { Input, inputClass } from '@/components/ui/input'
import { Card } from '@/components/ui/card'
import { extractValidationError } from '@/lib/handle-api-error'
import { cn } from '@/lib/utils'
import { ConfirmDelete } from '@/components/app/confirm-delete'
import { SectionTitle } from '@/components/app/section-title'
import { Segmented } from '@/components/app/segmented'
import { Spinner } from '@/components/app/spinner'
import { initials } from '@/components/shell/nav'
import { SensorCalibrationModal } from '@/components/settings/sensor-calibration-modal'

interface SettingsPageProps {
  theme: ThemeChoice
  setTheme: (theme: ThemeChoice) => void
  onLogout: () => void
}

// A blank field clears the key; otherwise it must match Pushover's exact format,
// mirroring the server rule so a bad key is caught before the request.
const keySchema = z.object({
  pushover_user_key: z
    .string()
    .trim()
    .refine(value => value === '' || /^[A-Za-z0-9]{30}$/.test(value), {
      message: 'Enter your 30-character Pushover key, or leave it blank to clear.',
    }),
})

type KeyValues = z.infer<typeof keySchema>

function PushoverKeyForm({
  initialKey,
  onSave,
}: {
  initialKey: string | null
  onSave: (key: string | null) => Promise<unknown>
}) {
  const [saved, setSaved] = useState(false)
  const flash = useRef<ReturnType<typeof setTimeout>>(undefined)
  useEffect(() => () => clearTimeout(flash.current), [])

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<KeyValues>({
    resolver: zodResolver(keySchema),
    defaultValues: { pushover_user_key: initialKey ?? '' },
  })

  const submit = async (values: KeyValues) => {
    const key = values.pushover_user_key.trim()
    try {
      await onSave(key === '' ? null : key)
      setSaved(true)
      clearTimeout(flash.current)
      flash.current = setTimeout(() => setSaved(false), 1600)
    } catch (err) {
      setError('pushover_user_key', {
        message: extractValidationError(
          err,
          'pushover_user_key',
          'Could not save. Please try again.'
        ),
      })
    }
  }

  const errorMessage = errors.pushover_user_key?.message

  return (
    <form onSubmit={handleSubmit(submit)}>
      <label htmlFor="pushover-key" className="mb-1.5 flex items-baseline gap-2">
        <span className="text-[13px] font-medium text-text">Pushover user key</span>
        <span className="text-[12px] text-text-subtle">for care reminders</span>
      </label>
      <div className="flex gap-2">
        <Input
          dusk="pushover-key"
          id="pushover-key"
          {...register('pushover_user_key')}
          placeholder="30-character key from Pushover"
          autoComplete="off"
          autoCapitalize="none"
          spellCheck={false}
          aria-invalid={errorMessage ? true : undefined}
          aria-describedby={errorMessage ? 'pushover-key-error' : undefined}
          className="flex-1 tnum"
        />
        <TooltipButton
          dusk="pushover-save"
          type="submit"
          disabled={isSubmitting}
          className="shrink-0"
          tooltipContent={isSubmitting ? 'Saving...' : undefined}
        >
          {saved ? (
            <>
              <Check size={16} />
              Saved
            </>
          ) : (
            'Save'
          )}
        </TooltipButton>
      </div>
      {errorMessage && (
        <div id="pushover-key-error" role="alert" className="mt-1 text-[12px] text-overdue">
          {errorMessage}
        </div>
      )}
      <p className="mt-2 text-[12px] text-text-subtle">
        Reminders are sent to your device when a plant is due, on the schedule each plant derives
        from its own history. Leave this blank to stop receiving them.
      </p>
    </form>
  )
}

function TagRow({
  tag,
  onUpdate,
  onDelete,
}: {
  tag: Tag
  onUpdate: (id: number, name: string) => Promise<unknown>
  onDelete: (id: number) => void
}) {
  const [editing, setEditing] = useState(false)
  const [name, setName] = useState(tag.name)
  const [error, setError] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (editing) inputRef.current?.focus()
  }, [editing])

  const save = async () => {
    const trimmed = name.trim()
    if (!trimmed) {
      setError('Name is required.')
      return
    }
    try {
      await onUpdate(tag.id, trimmed)
      setEditing(false)
      setError(null)
    } catch (err) {
      setError(extractValidationError(err, 'name', 'That name is taken.'))
    }
  }

  const cancel = () => {
    setName(tag.name)
    setEditing(false)
    setError(null)
  }

  return (
    <div dusk="tag-item" className="flex items-center gap-2 py-1.5">
      <span
        className="h-3 w-3 rounded-full shrink-0"
        style={{ background: tag.color || 'var(--series-1)' }}
      />
      {editing ? (
        <div className="flex-1 flex items-center gap-1.5">
          <Input
            dusk="tag-rename"
            ref={inputRef}
            value={name}
            onChange={e => {
              setName(e.target.value)
              setError(null)
            }}
            onKeyDown={e => {
              if (e.key === 'Enter') save()
              if (e.key === 'Escape') cancel()
            }}
            className="h-7 text-[13px] flex-1"
            aria-invalid={error ? true : undefined}
          />
          <button type="button" onClick={save} className="p-1 text-primary hover:text-primary/80">
            <Check size={14} />
          </button>
          <button type="button" onClick={cancel} className="p-1 text-text-muted hover:text-text">
            <X size={14} />
          </button>
        </div>
      ) : (
        <>
          <span className="flex-1 text-[13px]">{tag.name}</span>
          <button
            type="button"
            onClick={() => setEditing(true)}
            className="p-1 text-text-muted hover:text-text"
            aria-label={`Rename ${tag.name}`}
          >
            <Pencil size={13} />
          </button>
          <button
            dusk="tag-delete"
            type="button"
            onClick={() => onDelete(tag.id)}
            className="p-1 text-text-muted hover:text-overdue"
            aria-label={`Delete ${tag.name}`}
          >
            <Trash2 size={13} />
          </button>
        </>
      )}
      {error && <span className="text-[11px] text-overdue">{error}</span>}
    </div>
  )
}

function TagManager() {
  const { data: tags, loading } = useTags()
  const createTag = useCreateTag()
  const updateTag = useUpdateTag()
  const deleteTagMut = useDeleteTag()
  const [adding, setAdding] = useState(false)
  const [newName, setNewName] = useState('')
  const [createError, setCreateError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Tag | null>(null)
  const addRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (adding) addRef.current?.focus()
  }, [adding])

  const submitNew = async () => {
    const trimmed = newName.trim()
    if (!trimmed) return
    try {
      await createTag.mutateAsync(trimmed)
      setNewName('')
      setAdding(false)
      setCreateError(null)
    } catch (err) {
      setCreateError(extractValidationError(err, 'name', 'That name is taken.'))
    }
  }

  const handleUpdate = (id: number, name: string) =>
    updateTag.mutateAsync({ id, payload: { name } })

  const confirmDelete = () => {
    if (deleteTarget) {
      deleteTagMut.mutate(deleteTarget.id)
      setDeleteTarget(null)
    }
  }

  if (loading) return <Spinner />

  return (
    <>
      {(tags ?? []).length === 0 && !adding && (
        <p className="text-[13px] text-text-muted">
          No tags yet. Create one to start grouping your plants.
        </p>
      )}
      <div className="divide-y divide-border">
        {(tags ?? []).map(t => (
          <TagRow
            key={t.id}
            tag={t}
            onUpdate={handleUpdate}
            onDelete={id => {
              const tag = (tags ?? []).find(x => x.id === id)
              if (tag) setDeleteTarget(tag)
            }}
          />
        ))}
      </div>
      {adding ? (
        <div className="mt-2">
          <div className="flex items-center gap-1.5">
            <Input
              ref={addRef}
              value={newName}
              onChange={e => {
                setNewName(e.target.value)
                setCreateError(null)
              }}
              onKeyDown={e => {
                if (e.key === 'Enter') submitNew()
                if (e.key === 'Escape') {
                  setAdding(false)
                  setNewName('')
                  setCreateError(null)
                }
              }}
              placeholder="Tag name"
              className="h-8 text-[13px] flex-1"
            />
            <TooltipButton
              dusk="tag-submit"
              size="sm"
              onClick={submitNew}
              disabled={createTag.isPending || !newName.trim()}
              tooltipContent={
                createTag.isPending ? 'Saving...' : !newName.trim() ? 'Enter a tag name' : undefined
              }
            >
              Add
            </TooltipButton>
            <button
              type="button"
              onClick={() => {
                setAdding(false)
                setNewName('')
                setCreateError(null)
              }}
              className="p-1 text-text-muted hover:text-text"
            >
              <X size={14} />
            </button>
          </div>
          {createError && <div className="mt-1 text-[11px] text-overdue">{createError}</div>}
        </div>
      ) : (
        <button
          dusk="tag-add"
          type="button"
          onClick={() => setAdding(true)}
          className="mt-2 flex items-center gap-1.5 text-[13px] text-text-muted hover:text-text transition-colors"
        >
          <Plus size={14} />
          Add a tag
        </button>
      )}
      <ConfirmDelete
        open={deleteTarget !== null}
        onClose={() => setDeleteTarget(null)}
        onConfirm={confirmDelete}
        label={
          deleteTarget
            ? `"${deleteTarget.name}" will be removed from all plants that use it.`
            : undefined
        }
      />
    </>
  )
}

function EquipmentRow({
  equipment,
  onUpdate,
  onDelete,
}: {
  equipment: EquipmentOption
  onUpdate: (id: number, label: string) => Promise<unknown>
  onDelete: (id: number) => void
}) {
  const [editing, setEditing] = useState(false)
  const [label, setLabel] = useState(equipment.label)
  const [error, setError] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (editing) inputRef.current?.focus()
  }, [editing])

  const save = async () => {
    const trimmed = label.trim()
    if (!trimmed) {
      setError('Name is required.')
      return
    }
    try {
      await onUpdate(equipment.id, trimmed)
      setEditing(false)
      setError(null)
    } catch (err) {
      setError(extractValidationError(err, 'label', 'That name is taken.'))
    }
  }

  const cancel = () => {
    setLabel(equipment.label)
    setEditing(false)
    setError(null)
  }

  return (
    <div className="flex items-center gap-2 py-1.5">
      <Settings2 size={14} className="shrink-0 text-text-subtle" />
      {editing ? (
        <div className="flex-1 flex items-center gap-1.5">
          <Input
            ref={inputRef}
            value={label}
            onChange={e => {
              setLabel(e.target.value)
              setError(null)
            }}
            onKeyDown={e => {
              if (e.key === 'Enter') save()
              if (e.key === 'Escape') cancel()
            }}
            className="h-7 text-[13px] flex-1"
            aria-invalid={error ? true : undefined}
          />
          <button type="button" onClick={save} className="p-1 text-primary hover:text-primary/80">
            <Check size={14} />
          </button>
          <button type="button" onClick={cancel} className="p-1 text-text-muted hover:text-text">
            <X size={14} />
          </button>
        </div>
      ) : (
        <>
          <span className="flex-1 text-[13px]">{equipment.label}</span>
          <button
            type="button"
            onClick={() => setEditing(true)}
            className="p-1 text-text-muted hover:text-text"
            aria-label={`Rename ${equipment.label}`}
          >
            <Pencil size={13} />
          </button>
          <button
            type="button"
            onClick={() => onDelete(equipment.id)}
            className="p-1 text-text-muted hover:text-overdue"
            aria-label={`Delete ${equipment.label}`}
          >
            <Trash2 size={13} />
          </button>
        </>
      )}
      {error && <span className="text-[11px] text-overdue">{error}</span>}
    </div>
  )
}

function EquipmentManager() {
  const { data: equipment, loading } = useEquipment()
  const createEquipmentMut = useCreateEquipment()
  const updateEquipmentMut = useUpdateEquipment()
  const deleteEquipmentMut = useDeleteEquipment()
  const [adding, setAdding] = useState(false)
  const [newLabel, setNewLabel] = useState('')
  const [createError, setCreateError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<EquipmentOption | null>(null)
  const addRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (adding) addRef.current?.focus()
  }, [adding])

  const submitNew = async () => {
    const trimmed = newLabel.trim()
    if (!trimmed) return
    try {
      await createEquipmentMut.mutateAsync(trimmed)
      setNewLabel('')
      setAdding(false)
      setCreateError(null)
    } catch (err) {
      setCreateError(extractValidationError(err, 'label', 'That name is taken.'))
    }
  }

  const handleUpdate = (id: number, label: string) =>
    updateEquipmentMut.mutateAsync({ id, payload: { label } })

  const confirmDelete = () => {
    if (deleteTarget) {
      deleteEquipmentMut.mutate(deleteTarget.id)
      setDeleteTarget(null)
    }
  }

  if (loading) return <Spinner />

  return (
    <>
      {equipment.length === 0 && !adding && (
        <p className="text-[13px] text-text-muted">
          No equipment yet. Create one to start tracking what your plants use.
        </p>
      )}
      <div className="divide-y divide-border">
        {equipment.map(eq => (
          <EquipmentRow
            key={eq.id}
            equipment={eq}
            onUpdate={handleUpdate}
            onDelete={id => {
              const item = equipment.find(x => x.id === id)
              if (item) setDeleteTarget(item)
            }}
          />
        ))}
      </div>
      {adding ? (
        <div className="mt-2">
          <div className="flex items-center gap-1.5">
            <Input
              ref={addRef}
              value={newLabel}
              onChange={e => {
                setNewLabel(e.target.value)
                setCreateError(null)
              }}
              onKeyDown={e => {
                if (e.key === 'Enter') submitNew()
                if (e.key === 'Escape') {
                  setAdding(false)
                  setNewLabel('')
                  setCreateError(null)
                }
              }}
              placeholder="Equipment name"
              className="h-8 text-[13px] flex-1"
            />
            <TooltipButton
              size="sm"
              onClick={submitNew}
              disabled={createEquipmentMut.isPending || !newLabel.trim()}
              tooltipContent={
                createEquipmentMut.isPending
                  ? 'Saving...'
                  : !newLabel.trim()
                    ? 'Enter equipment name'
                    : undefined
              }
            >
              Add
            </TooltipButton>
            <button
              type="button"
              onClick={() => {
                setAdding(false)
                setNewLabel('')
                setCreateError(null)
              }}
              className="p-1 text-text-muted hover:text-text"
            >
              <X size={14} />
            </button>
          </div>
          {createError && <div className="mt-1 text-[11px] text-overdue">{createError}</div>}
        </div>
      ) : (
        <button
          dusk="equipment-add"
          type="button"
          onClick={() => setAdding(true)}
          className="mt-2 flex items-center gap-1.5 text-[13px] text-text-muted hover:text-text transition-colors"
        >
          <Plus size={14} />
          Add equipment
        </button>
      )}
      <ConfirmDelete
        open={deleteTarget !== null}
        onClose={() => setDeleteTarget(null)}
        onConfirm={confirmDelete}
        label={
          deleteTarget
            ? `"${deleteTarget.label}" will be removed from all plants that use it.`
            : undefined
        }
      />
    </>
  )
}

function SensorRow({
  sensor,
  offline,
  discoveryLoaded,
  onUpdate,
  onDelete,
  onCalibrate,
}: {
  sensor: Sensor
  offline: boolean
  discoveryLoaded: boolean
  onUpdate: (id: number, payload: { name?: string; location?: string | null }) => Promise<unknown>
  onDelete: (id: number) => void
  onCalibrate: (sensor: Sensor) => void
}) {
  const [editing, setEditing] = useState(false)
  const [name, setName] = useState(sensor.name)
  const [location, setLocation] = useState(sensor.location ?? '')
  const [error, setError] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (editing) inputRef.current?.focus()
  }, [editing])

  const save = async () => {
    const trimmedName = name.trim()
    if (!trimmedName) {
      setError('Name is required.')
      return
    }
    try {
      await onUpdate(sensor.id, {
        name: trimmedName,
        location: location.trim() || null,
      })
      setEditing(false)
      setError(null)
    } catch (err) {
      setError(extractValidationError(err, 'name', 'Could not save.'))
    }
  }

  const cancel = () => {
    setName(sensor.name)
    setLocation(sensor.location ?? '')
    setEditing(false)
    setError(null)
  }

  return (
    <div className="flex items-center gap-2 py-1.5">
      <span className="h-3 w-3 rounded-full shrink-0" style={{ background: sensor.color }} />
      {editing ? (
        <div className="flex-1 space-y-1">
          <div className="flex items-center gap-1.5">
            <Input
              ref={inputRef}
              value={name}
              onChange={e => {
                setName(e.target.value)
                setError(null)
              }}
              onKeyDown={e => {
                if (e.key === 'Enter') save()
                if (e.key === 'Escape') cancel()
              }}
              placeholder="Name"
              className="h-7 text-[13px] flex-1"
              aria-invalid={error ? true : undefined}
            />
            <Input
              value={location}
              onChange={e => setLocation(e.target.value)}
              onKeyDown={e => {
                if (e.key === 'Enter') save()
                if (e.key === 'Escape') cancel()
              }}
              placeholder="Location"
              className="h-7 text-[13px] w-28"
            />
            <button type="button" onClick={save} className="p-1 text-primary hover:text-primary/80">
              <Check size={14} />
            </button>
            <button type="button" onClick={cancel} className="p-1 text-text-muted hover:text-text">
              <X size={14} />
            </button>
          </div>
          {error && <span className="text-[11px] text-overdue">{error}</span>}
        </div>
      ) : (
        <>
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-1.5">
              <span className="text-[13px] truncate">{sensor.name}</span>
              {discoveryLoaded && offline && (
                <span className="text-[10px] px-1 py-0.5 rounded bg-text-subtle/15 text-text-muted">
                  offline
                </span>
              )}
            </div>
            <div className="text-[11px] text-text-muted truncate">
              {sensor.type.charAt(0).toUpperCase() + sensor.type.slice(1)}
              {sensor.hardware_type && <> &middot; {sensor.hardware_type}</>} &middot; {sensor.mac}
              {sensor.location && <> &middot; {sensor.location}</>}
              {sensor.plant_count > 0 && (
                <>
                  {' '}
                  &middot; {sensor.plant_count} plant{sensor.plant_count !== 1 ? 's' : ''}
                </>
              )}
            </div>
          </div>
          {sensor.type === 'moisture' && (
            <button
              type="button"
              onClick={() => onCalibrate(sensor)}
              className="p-1 text-text-muted hover:text-text"
              aria-label={`Calibrate ${sensor.name}`}
              dusk="sensor-calibrate"
            >
              <Settings size={13} />
            </button>
          )}
          <button
            type="button"
            onClick={() => setEditing(true)}
            className="p-1 text-text-muted hover:text-text"
            aria-label={`Edit ${sensor.name}`}
          >
            <Pencil size={13} />
          </button>
          <button
            type="button"
            onClick={() => onDelete(sensor.id)}
            className="p-1 text-text-muted hover:text-overdue"
            aria-label={`Delete ${sensor.name}`}
          >
            <Trash2 size={13} />
          </button>
        </>
      )}
    </div>
  )
}

function DiscoverRow({
  device,
  onRegister,
}: {
  device: DiscoveredSensor
  onRegister: (device: DiscoveredSensor) => void
}) {
  return (
    <div className="flex items-center gap-2 py-1.5">
      <Radio size={14} className="shrink-0 text-text-subtle" />
      <div className="flex-1 min-w-0">
        <span className="text-[13px] truncate block">{device.device_name ?? device.mac}</span>
        <span className="text-[11px] text-text-muted">{device.mac}</span>
      </div>
      <Button size="sm" onClick={() => onRegister(device)}>
        Register
      </Button>
    </div>
  )
}

function RegisterForm({ device, onClose }: { device: DiscoveredSensor; onClose: () => void }) {
  const createSensor = useCreateSensor()
  const { data: sensorTypes } = useSensorTypes()
  const [name, setName] = useState('')
  const [location, setLocation] = useState('')
  const [type, setType] = useState(device.suggested_type ?? '')
  const [error, setError] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    inputRef.current?.focus()
  }, [])

  // Auto-select when only one type is available; show the chooser when a second arrives.
  const resolvedType = type || (sensorTypes.length === 1 ? (sensorTypes[0]?.value ?? '') : '')

  const submit = async () => {
    const trimmed = name.trim()
    if (!trimmed) {
      setError('Name is required.')
      return
    }
    if (!resolvedType) {
      setError('Select a sensor type.')
      return
    }
    try {
      await createSensor.mutateAsync({
        mac: device.mac,
        device_name: device.device_name,
        hardware_type: device.sensor_type,
        name: trimmed,
        location: location.trim() || null,
        type: resolvedType,
      })
      onClose()
    } catch (err) {
      setError(extractValidationError(err, ['name', 'mac', 'type'], 'Could not register.'))
    }
  }

  return (
    <div className="border border-border rounded p-3 space-y-2">
      <div className="text-[12px] text-text-muted">Registering {device.mac}</div>
      <Input
        ref={inputRef}
        value={name}
        onChange={e => {
          setName(e.target.value)
          setError(null)
        }}
        onKeyDown={e => {
          if (e.key === 'Enter') submit()
          if (e.key === 'Escape') onClose()
        }}
        placeholder="Name (required)"
        className="h-8 text-[13px]"
      />
      <select
        value={resolvedType}
        onChange={e => {
          setType(e.target.value)
          setError(null)
        }}
        className={cn(inputClass, 'h-8 text-[13px]')}
      >
        <option value="" disabled>
          Select sensor type
        </option>
        {sensorTypes.map(t => (
          <option key={t.value} value={t.value}>
            {t.label}
          </option>
        ))}
      </select>
      <Input
        value={location}
        onChange={e => setLocation(e.target.value)}
        onKeyDown={e => {
          if (e.key === 'Enter') submit()
          if (e.key === 'Escape') onClose()
        }}
        placeholder="Location (optional)"
        className="h-8 text-[13px]"
      />
      {error && <div className="text-[11px] text-overdue">{error}</div>}
      <div className="flex gap-2">
        <TooltipButton
          dusk="sensor-save"
          size="sm"
          onClick={submit}
          disabled={createSensor.isPending || !name.trim() || !resolvedType}
          tooltipContent={
            createSensor.isPending
              ? 'Saving...'
              : !name.trim()
                ? 'Enter a sensor name'
                : !resolvedType
                  ? 'Select a sensor type'
                  : undefined
          }
        >
          Save
        </TooltipButton>
        <button
          type="button"
          onClick={onClose}
          className="text-[13px] text-text-muted hover:text-text"
        >
          Cancel
        </button>
      </div>
    </div>
  )
}

function SensorManager() {
  const { data: sensors, loading } = useSensors()
  const discovery = useDiscoverSensors()
  const testConn = useTestConnection()
  const updateSensor = useUpdateSensor()
  const deleteSensorMut = useDeleteSensor()
  const [deleteTarget, setDeleteTarget] = useState<Sensor | null>(null)
  const [registering, setRegistering] = useState<DiscoveredSensor | null>(null)
  const [calibrating, setCalibrating] = useState<Sensor | null>(null)

  const discoveredMacs = discovery.data?.data ?? []
  const discoveryLoaded = discovery.isSuccess

  const registeredMacs = new Set((sensors ?? []).map(s => s.mac))
  const unregistered = discoveredMacs.filter(d => !registeredMacs.has(d.mac))

  const offlineMacs = new Set(
    discoveryLoaded
      ? (sensors ?? []).filter(s => !discoveredMacs.some(d => d.mac === s.mac)).map(s => s.mac)
      : []
  )

  const handleUpdate = (id: number, payload: { name?: string; location?: string | null }) =>
    updateSensor.mutateAsync({ id, payload })

  const confirmDelete = () => {
    if (deleteTarget) {
      deleteSensorMut.mutate(deleteTarget.id)
      setDeleteTarget(null)
    }
  }

  const statusColor =
    testConn.data?.status === 'connected'
      ? 'text-ok'
      : testConn.data
        ? 'text-overdue'
        : 'text-text-muted'

  const statusLabel = testConn.data
    ? testConn.data.status === 'connected'
      ? `Connected (${testConn.data.sensors_seen ?? 0} sensor${testConn.data.sensors_seen !== 1 ? 's' : ''} seen)`
      : (testConn.data.error ?? testConn.data.status.replace('_', ' '))
    : null

  return (
    <div className="space-y-4">
      {/* Gateway connection */}
      <div>
        <div className="flex items-center gap-2 mb-2">
          <TooltipButton
            dusk="test-connection"
            size="sm"
            variant="outline"
            onClick={() => testConn.mutate()}
            disabled={testConn.isPending}
            tooltipContent={testConn.isPending ? 'Testing...' : undefined}
          >
            {testConn.isPending ? 'Testing...' : 'Test Connection'}
          </TooltipButton>
          {statusLabel && <span className={`text-[12px] ${statusColor}`}>{statusLabel}</span>}
        </div>
      </div>

      {/* Sensor registry */}
      {loading ? (
        <Spinner />
      ) : (
        <>
          {(sensors ?? []).length === 0 && (
            <p className="text-[13px] text-text-muted">
              No sensors registered yet. Use Discover to find sensors on your gateway.
            </p>
          )}
          <div className="divide-y divide-border">
            {(sensors ?? []).map(s => (
              <SensorRow
                key={s.id}
                sensor={s}
                offline={offlineMacs.has(s.mac)}
                discoveryLoaded={discoveryLoaded}
                onUpdate={handleUpdate}
                onDelete={id => {
                  const found = (sensors ?? []).find(x => x.id === id)
                  if (found) setDeleteTarget(found)
                }}
                onCalibrate={setCalibrating}
              />
            ))}
          </div>

          {/* Discovery */}
          <div className="pt-2 border-t border-border">
            <TooltipButton
              dusk="discover-sensors"
              size="sm"
              variant="outline"
              onClick={() => discovery.refetch()}
              disabled={discovery.isFetching}
              tooltipContent={discovery.isFetching ? 'Scanning...' : undefined}
            >
              {discovery.isFetching ? 'Scanning...' : 'Discover Sensors'}
            </TooltipButton>
            {discovery.error && (
              <div className="mt-1 text-[11px] text-overdue">Could not reach gateway.</div>
            )}
            {discoveryLoaded && discovery.data?.error && (
              <p className="mt-2 text-[12px] text-overdue">
                {discovery.data.error}. Connect the gateway first.
              </p>
            )}
            {discoveryLoaded && !discovery.data?.error && unregistered.length === 0 && (
              <p className="mt-2 text-[12px] text-text-muted">No unregistered sensors found.</p>
            )}
            {unregistered.length > 0 && (
              <div className="mt-2 divide-y divide-border">
                {unregistered.map(d => (
                  <DiscoverRow key={d.mac} device={d} onRegister={setRegistering} />
                ))}
              </div>
            )}
            {registering && (
              <div className="mt-2">
                <RegisterForm device={registering} onClose={() => setRegistering(null)} />
              </div>
            )}
          </div>
        </>
      )}

      <ConfirmDelete
        open={deleteTarget !== null}
        onClose={() => setDeleteTarget(null)}
        onConfirm={confirmDelete}
        label={
          deleteTarget
            ? `"${deleteTarget.name}" and all its readings will be permanently deleted.`
            : undefined
        }
      />
      {calibrating && (
        <SensorCalibrationModal sensor={calibrating} onClose={() => setCalibrating(null)} />
      )}
    </div>
  )
}

export function SettingsPage({ theme, setTheme, onLogout }: SettingsPageProps) {
  const { data: settings, loading, error } = useSettings()
  const { user } = useCurrentUser()
  const update = useUpdateSettings()

  return (
    <div className="max-w-xl space-y-5">
      <h1 className="text-2xl font-semibold">Settings</h1>
      <Card className="p-4">
        <SectionTitle icon={Bell}>Notifications</SectionTitle>
        {loading ? (
          <div className="py-2">
            <Spinner />
          </div>
        ) : error || !settings ? (
          <div className="flex items-center gap-1.5 text-[13px] text-overdue">
            <AlertTriangle size={14} />
            Unable to load your reminder settings.
          </div>
        ) : (
          <PushoverKeyForm
            initialKey={settings.pushover_user_key}
            onSave={key => update.mutateAsync({ pushover_user_key: key })}
          />
        )}
      </Card>
      <Card className="p-4">
        <SectionTitle icon={Tags}>Tags</SectionTitle>
        <TagManager />
      </Card>
      <Card className="p-4">
        <SectionTitle icon={Settings2}>Equipment</SectionTitle>
        <EquipmentManager />
      </Card>
      <Card className="p-4">
        <SectionTitle icon={Radio}>Sensors</SectionTitle>
        <SensorManager />
      </Card>
      <Card className="p-4" dusk="settings-theme">
        <SectionTitle icon={Sun}>Appearance</SectionTitle>
        <Segmented
          value={theme}
          onChange={v => setTheme(v as ThemeChoice)}
          options={[
            { value: 'light', label: 'Light', dusk: 'theme-light' },
            { value: 'dark', label: 'Dark', dusk: 'theme-dark' },
            { value: 'system', label: 'Follow system', dusk: 'theme-system' },
          ]}
        />
      </Card>
      <Card className="p-4">
        <SectionTitle icon={UserIcon}>Account</SectionTitle>
        {user && (
          <div className="mb-3 flex items-center gap-3">
            <span className="grid h-11 w-11 place-items-center rounded-full bg-primary/15 font-semibold text-primary">
              {initials(user.name)}
            </span>
            <div>
              <div dusk="account-name" className="font-medium">
                {user.name}
              </div>
              <div dusk="account-email" className="tnum text-[12px] text-text-muted">
                {user.email}
              </div>
            </div>
          </div>
        )}
        <Button variant="outline" onClick={onLogout}>
          <LogOut size={16} />
          Log out
        </Button>
      </Card>
      <p className="text-center text-[11px] text-text-subtle">
        Foliotrak · self-hosted, LAN-first plant care
      </p>
    </div>
  )
}
