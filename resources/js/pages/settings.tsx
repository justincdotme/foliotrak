import { useEffect, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { isAxiosError } from 'axios'
import {
  AlertTriangle,
  Bell,
  Check,
  LogOut,
  Pencil,
  Plus,
  Settings2,
  Sun,
  Tags,
  Trash2,
  User as UserIcon,
  X,
} from 'lucide-react'
import type { ThemeChoice } from '@/hooks/useTheme'
import type { EquipmentOption, Tag } from '@/api/types'
import { useSettings, useUpdateSettings } from '@/hooks/useSettings'
import { useCurrentUser } from '@/hooks/useCurrentUser'
import { useTags, useCreateTag, useUpdateTag, useDeleteTag } from '@/hooks/useTags'
import {
  useEquipment,
  useCreateEquipment,
  useUpdateEquipment,
  useDeleteEquipment,
} from '@/hooks/useEquipment'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card } from '@/components/ui/card'
import { ConfirmDelete } from '@/components/app/confirm-delete'
import { SectionTitle } from '@/components/app/section-title'
import { Segmented } from '@/components/app/segmented'
import { Spinner } from '@/components/app/spinner'
import { initials } from '@/components/shell/nav'

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

function serverError(err: unknown): string {
  if (isAxiosError(err) && err.response?.status === 422) {
    const errors = err.response.data?.errors?.pushover_user_key
    if (Array.isArray(errors) && errors[0]) return errors[0]
  }
  return 'Could not save. Please try again.'
}

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
      setError('pushover_user_key', { message: serverError(err) })
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
        <Button type="submit" disabled={isSubmitting} className="shrink-0">
          {saved ? (
            <>
              <Check size={16} />
              Saved
            </>
          ) : (
            'Save'
          )}
        </Button>
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
      if (isAxiosError(err) && err.response?.status === 422) {
        const messages = err.response.data?.errors?.name
        setError(Array.isArray(messages) ? messages[0] : 'That name is taken.')
      } else {
        setError('Could not rename. Try again.')
      }
    }
  }

  const cancel = () => {
    setName(tag.name)
    setEditing(false)
    setError(null)
  }

  return (
    <div className="flex items-center gap-2 py-1.5">
      <span
        className="h-3 w-3 rounded-full shrink-0"
        style={{ background: tag.color || 'var(--series-1)' }}
      />
      {editing ? (
        <div className="flex-1 flex items-center gap-1.5">
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
      if (isAxiosError(err) && err.response?.status === 422) {
        const messages = err.response.data?.errors?.name
        setCreateError(Array.isArray(messages) ? messages[0] : 'That name is taken.')
      } else {
        setCreateError('Could not create tag. Try again.')
      }
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
            <Button size="sm" onClick={submitNew} disabled={createTag.isPending || !newName.trim()}>
              Add
            </Button>
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
      if (isAxiosError(err) && err.response?.status === 422) {
        const messages = err.response.data?.errors?.label
        setError(Array.isArray(messages) ? messages[0] : 'That name is taken.')
      } else {
        setError('Could not rename. Try again.')
      }
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
      if (isAxiosError(err) && err.response?.status === 422) {
        const messages = err.response.data?.errors?.label
        setCreateError(Array.isArray(messages) ? messages[0] : 'That name is taken.')
      } else {
        setCreateError('Could not create equipment. Try again.')
      }
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
            <Button
              size="sm"
              onClick={submitNew}
              disabled={createEquipmentMut.isPending || !newLabel.trim()}
            >
              Add
            </Button>
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
        <SectionTitle icon={Sun}>Appearance</SectionTitle>
        <Segmented
          value={theme}
          onChange={v => setTheme(v as ThemeChoice)}
          options={[
            { value: 'light', label: 'Light' },
            { value: 'dark', label: 'Dark' },
            { value: 'system', label: 'Follow system' },
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
              <div className="font-medium">{user.name}</div>
              <div className="tnum text-[12px] text-text-muted">{user.email}</div>
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
