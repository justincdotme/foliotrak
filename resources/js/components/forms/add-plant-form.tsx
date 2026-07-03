import { useState, type ChangeEvent, type KeyboardEvent, type SyntheticEvent } from 'react'
import { AlertTriangle, Check, ImageIcon, Plus, Search } from 'lucide-react'
import type { CropArea, SpeciesSuggestion, Tag } from '@/api/types'
import { cn } from '@/lib/utils'
import { useSpeciesSuggest } from '@/hooks/useSpeciesSuggest'
import { useTags } from '@/hooks/useTags'
import { useCreatePlant } from '@/hooks/usePlantMutations'
import { inputClass } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/app/field'
import { Input } from '@/components/ui/input'
import { LocationCombobox } from '@/components/app/location-combobox'
import { TagInlineCreate } from '@/components/app/tag-inline-create'
import { CropWorkflow } from '@/components/plant/crop-workflow'
import { handleApiError } from '@/lib/handle-api-error'

interface AddPlantFormProps {
  onDone: () => void
}

export function AddPlantForm({ onDone }: AddPlantFormProps) {
  const { data: allTags } = useTags()
  const create = useCreatePlant()
  const [common, setCommon] = useState('')
  const [sci, setSci] = useState('')
  const [nickname, setNickname] = useState('')
  const [gbifKey, setGbifKey] = useState<string | null>(null)
  const [locationId, setLocationId] = useState<number | null>(null)
  const [acquired, setAcquired] = useState('')
  const [selectedTags, setSelectedTags] = useState<Tag[]>([])
  const [photoFile, setPhotoFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<string | null>(null)
  const [cropping, setCropping] = useState(false)
  const [heroCrop, setHeroCrop] = useState<CropArea | null>(null)
  const [thumbCrop, setThumbCrop] = useState<CropArea | null>(null)
  const [open, setOpen] = useState(false)
  const [active, setActive] = useState(-1)
  const [formError, setFormError] = useState<string | null>(null)
  const [partialError, setPartialError] = useState<string | null>(null)
  const { results, loading } = useSpeciesSuggest(common)

  const matchedAlias = (g: SpeciesSuggestion): string | null => {
    const q = common.trim().toLowerCase()
    if (!q || !g.common_names?.length) return null
    if (g.common_name?.toLowerCase().includes(q)) return null
    return g.common_names.find(n => n.toLowerCase().includes(q)) ?? null
  }

  const pick = (g: SpeciesSuggestion) => {
    setCommon(g.common_name || g.canonical_name || '')
    setSci(g.canonical_name || '')
    setGbifKey(g.gbif_key)
    setOpen(false)
    setActive(-1)
  }

  const keep = () => {
    setGbifKey(null)
    setOpen(false)
  }

  const toggleTag = (t: Tag) => {
    setSelectedTags(ts =>
      ts.find(x => x.id === t.id) ? ts.filter(x => x.id !== t.id) : [...ts, t]
    )
  }

  // The API rejects an uncropped cover, so a picked file goes straight into the
  // crop workflow and an aborted crop discards the file.
  const startCrop = (f: File) => {
    if (preview) URL.revokeObjectURL(preview)
    setPhotoFile(f)
    setHeroCrop(null)
    setThumbCrop(null)
    setPreview(URL.createObjectURL(f))
    setCropping(true)
  }

  const abortCrop = () => {
    setCropping(false)
    setPhotoFile(null)
    setHeroCrop(null)
    setThumbCrop(null)
    if (preview) URL.revokeObjectURL(preview)
    setPreview(null)
  }

  const finishCrop = (hero: CropArea, thumb: CropArea) => {
    setHeroCrop(hero)
    setThumbCrop(thumb)
    setCropping(false)
    if (preview) URL.revokeObjectURL(preview)
    setPreview(null)
  }

  const onKey = (e: KeyboardEvent<HTMLInputElement>) => {
    if (!open) return
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setActive(a => Math.min(a + 1, results.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setActive(a => Math.max(a - 1, 0))
    } else if (e.key === 'Enter') {
      if (active >= 0 && results[active]) {
        e.preventDefault()
        pick(results[active])
      }
    } else if (e.key === 'Escape') {
      setOpen(false)
    }
  }

  const submit = async (e: SyntheticEvent) => {
    e.preventDefault()
    if (!common.trim()) return
    setFormError(null)
    try {
      const { coverUploadFailed } = await create.mutateAsync({
        payload: {
          common_name: common.trim(),
          scientific_name: sci || null,
          nickname: nickname.trim() || null,
          gbif_key: gbifKey,
          location_id: locationId,
          acquired_on: acquired || null,
          tag_ids: selectedTags.map(t => t.id),
        },
        cover: photoFile && heroCrop && thumbCrop ? { file: photoFile, heroCrop, thumbCrop } : null,
      })
      if (coverUploadFailed) {
        setPartialError(
          "The plant was added, but the cover photo didn't upload. Set it from the plant's page."
        )
        return
      }
      onDone()
    } catch (err) {
      setFormError(handleApiError(err))
    }
  }

  return (
    <form onSubmit={submit} className="space-y-4">
      <Field label="Name" required hint="search a species or type your own">
        <div className="relative">
          <div className="relative">
            <Search
              size={16}
              className="absolute left-3 top-1/2 -translate-y-1/2 text-text-subtle"
            />
            <input
              value={common}
              onChange={e => {
                setCommon(e.target.value)
                setOpen(true)
                setGbifKey(null)
              }}
              onFocus={() => setOpen(true)}
              onKeyDown={onKey}
              placeholder="Pothos, Monstera, snake plant…"
              className={cn(inputClass, 'pl-9')}
              role="combobox"
              aria-expanded={open}
              aria-autocomplete="list"
              aria-controls="species-list"
              dusk="add-plant-name"
            />
          </div>
          {open && common.trim().length >= 2 && (
            <div
              id="species-list"
              className="absolute left-0 right-0 z-10 mt-1 overflow-hidden rounded-[10px] border border-border bg-surface-raised shadow-xl"
            >
              <div
                className="border-b border-border px-3 py-1.5 text-[11px] text-text-subtle"
                aria-live="polite"
              >
                {loading
                  ? 'Searching…'
                  : results.length + ' result' + (results.length === 1 ? '' : 's')}
              </div>
              {results.map((g, i) => (
                <button
                  key={g.gbif_key}
                  type="button"
                  onMouseEnter={() => setActive(i)}
                  onClick={() => pick(g)}
                  className={cn(
                    'w-full flex flex-col gap-0.5 px-3 py-2 text-left',
                    active === i ? 'bg-surface' : ''
                  )}
                >
                  <span className="text-[13px]">
                    <span className="italic">{g.canonical_name}</span>
                    {g.common_name && <span className="text-text-muted"> · {g.common_name}</span>}
                  </span>
                  {(() => {
                    const alias = matchedAlias(g)
                    return alias ? (
                      <span className="text-[11px] text-primary">also called {alias}</span>
                    ) : null
                  })()}
                  <span className="text-[11px] text-text-subtle">
                    {g.rank?.toLowerCase()} · {g.family}
                  </span>
                </button>
              ))}
              <button
                type="button"
                onClick={keep}
                className="w-full border-t border-border px-3 py-2 text-left text-[13px] text-text-muted hover:bg-surface flex items-center gap-2"
              >
                <Plus size={14} />
                Keep &quot;{common.trim()}&quot; as a custom name
              </button>
            </div>
          )}
        </div>
      </Field>
      <Field label="Scientific name" hint="italic, optional">
        <Input
          value={sci}
          onChange={e => setSci(e.target.value)}
          className="italic"
          placeholder="Epipremnum aureum"
        />
        {gbifKey && (
          <div className="mt-1 flex items-center gap-1 text-[11px] text-text-subtle">
            <Check size={12} className="text-primary" />
            Matched GBIF {gbifKey}
          </div>
        )}
      </Field>
      <Field label="Nickname" hint="optional">
        <Input
          value={nickname}
          onChange={e => setNickname(e.target.value)}
          placeholder="Kitchen Pothos, Big Fern…"
        />
      </Field>
      <Field label="Location" hint="optional">
        <LocationCombobox value={locationId} onChange={setLocationId} />
      </Field>
      <Field label="Acquired on" hint="optional">
        <Input type="date" value={acquired} onChange={e => setAcquired(e.target.value)} />
        {acquired && (
          <button
            type="button"
            onClick={() => setAcquired('')}
            className="mt-1 text-[11px] text-text-muted hover:text-text"
          >
            Clear
          </button>
        )}
      </Field>
      <Field label="Tags">
        <div className="flex flex-wrap gap-1.5">
          <TagInlineCreate
            allTags={allTags || []}
            selectedTags={selectedTags}
            onToggle={toggleTag}
          />
        </div>
      </Field>
      <Field label="Photo" hint="optional, becomes the cover">
        <label className="flex h-11 cursor-pointer items-center gap-2 rounded-[8px] border border-dashed border-border-strong bg-surface-raised px-3 text-text-muted hover:text-text">
          <ImageIcon size={16} />
          <span className="text-[13px] truncate">
            {photoFile ? `${photoFile.name} (cropped)` : 'Add a photo'}
          </span>
          <input
            type="file"
            accept="image/*"
            className="hidden"
            dusk="add-plant-photo"
            onChange={(e: ChangeEvent<HTMLInputElement>) => {
              const f = e.target.files?.[0]
              if (f) startCrop(f)
              e.target.value = ''
            }}
          />
        </label>
      </Field>
      {formError && (
        <div className="flex items-center gap-1.5 text-[12px] text-overdue">
          <AlertTriangle size={14} />
          {formError}
        </div>
      )}
      {partialError && (
        <div className="flex items-center gap-1.5 text-[12px] text-overdue">
          <AlertTriangle size={14} />
          {partialError}
        </div>
      )}
      <div className="flex justify-end gap-2 pt-1">
        {partialError ? (
          <Button type="button" onClick={onDone}>
            Done
          </Button>
        ) : (
          <Button
            type="submit"
            dusk="add-plant-submit"
            disabled={create.isPending || !common.trim()}
          >
            <Plus size={16} />
            Add plant
          </Button>
        )}
      </div>
      {cropping && preview && (
        <CropWorkflow
          preview={preview}
          onBack={abortCrop}
          onComplete={finishCrop}
          onClose={abortCrop}
          busy={false}
          failed={false}
        />
      )}
    </form>
  )
}
