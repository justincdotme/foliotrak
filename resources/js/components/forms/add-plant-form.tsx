import {
  useEffect,
  useState,
  type ChangeEvent,
  type MutableRefObject,
  type SyntheticEvent,
} from 'react'
import { Check, ImageIcon, Plus } from 'lucide-react'
import type { CropArea, PlantSensor, SpeciesSuggestion, Tag } from '@/api/types'
import { useSpeciesSuggest } from '@/hooks/useSpeciesSuggest'
import { useSensors } from '@/hooks/useSensors'
import { useTags } from '@/hooks/useTags'
import { useCreatePlant } from '@/hooks/usePlantMutations'
import { useCareFormSubmit } from '@/hooks/useCareFormSubmit'
import { Button } from '@/components/ui/button'
import { TooltipButton } from '@/components/ui/tooltip-button'
import { Field } from '@/components/app/field'
import { FormError } from '@/components/app/form-error'
import { InlineCombobox } from '@/components/app/inline-combobox'
import { Input } from '@/components/ui/input'
import { LocationCombobox } from '@/components/app/location-combobox'
import { SensorSelect } from '@/components/app/sensor-select'
import { TagInlineCreate } from '@/components/app/tag-inline-create'
import { CropWorkflow } from '@/components/plant/crop-workflow'

interface AddPlantFormProps {
  onDone: () => void
  dirtyRef?: MutableRefObject<boolean>
}

export function AddPlantForm({ onDone, dirtyRef }: AddPlantFormProps) {
  const { data: allTags } = useTags()
  const { data: allSensors } = useSensors()
  const create = useCreatePlant()
  const [common, setCommon] = useState('')
  const [sci, setSci] = useState('')
  const [nickname, setNickname] = useState('')
  const [gbifKey, setGbifKey] = useState<string | null>(null)
  const [locationId, setLocationId] = useState<number | null>(null)
  const [acquired, setAcquired] = useState('')
  const [selectedTags, setSelectedTags] = useState<Tag[]>([])
  const [selectedSensors, setSelectedSensors] = useState<PlantSensor[]>([])
  const [photoFile, setPhotoFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<string | null>(null)
  const [cropping, setCropping] = useState(false)
  const [heroCrop, setHeroCrop] = useState<CropArea | null>(null)
  const [thumbCrop, setThumbCrop] = useState<CropArea | null>(null)
  const [speciesOpen, setSpeciesOpen] = useState(false)
  const [partialError, setPartialError] = useState<string | null>(null)
  const { results, loading } = useSpeciesSuggest(common)

  const dirty = !!(
    common ||
    sci ||
    nickname ||
    gbifKey ||
    locationId ||
    acquired ||
    selectedTags.length ||
    selectedSensors.length ||
    photoFile
  )

  useEffect(() => {
    if (dirtyRef) dirtyRef.current = dirty
  }, [dirty, dirtyRef])

  const { submit, formError } = useCareFormSubmit({
    createFn: (args: any) => create.mutateAsync(args), // eslint-disable-line @typescript-eslint/no-explicit-any
  })

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
  }

  const keep = () => {
    setGbifKey(null)
    setSpeciesOpen(false)
  }

  const toggleTag = (t: Tag) => {
    setSelectedTags(ts =>
      ts.find(x => x.id === t.id) ? ts.filter(x => x.id !== t.id) : [...ts, t]
    )
  }

  const toggleSensor = (s: PlantSensor) => {
    setSelectedSensors(ss =>
      ss.find(x => x.id === s.id) ? ss.filter(x => x.id !== s.id) : [...ss, s]
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

  const handleFormSubmit = async (e: SyntheticEvent) => {
    e.preventDefault()
    if (!common.trim()) return
    await submit(
      {
        payload: {
          common_name: common.trim(),
          scientific_name: sci || null,
          nickname: nickname.trim() || null,
          gbif_key: gbifKey,
          location_id: locationId,
          acquired_on: acquired || null,
          tag_ids: selectedTags.map(t => t.id),
          sensor_ids: selectedSensors.map(s => s.id),
        },
        cover: photoFile && heroCrop && thumbCrop ? { file: photoFile, heroCrop, thumbCrop } : null,
      },
      result => {
        if (result.coverUploadFailed) {
          setPartialError(
            "The plant was added, but the cover photo didn't upload. Set it from the plant's page."
          )
          return
        }
        onDone()
      }
    )
  }

  return (
    <form onSubmit={handleFormSubmit} className="space-y-4">
      <Field label="Name" required hint="search a species or type your own">
        <InlineCombobox
          items={results}
          shouldFilter={false}
          query={common}
          onQueryChange={q => {
            setCommon(q)
            setGbifKey(null)
          }}
          open={speciesOpen && common.trim().length >= 2}
          onOpenChange={setSpeciesOpen}
          onSelect={pick}
          getItemValue={g => g.canonical_name || ''}
          renderItem={g => (
            <div className="flex flex-col gap-0.5" dusk="species-suggestion">
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
            </div>
          )}
          loading={loading}
          emptyMessage="No matches"
          placeholder="Pothos, Monstera, snake plant…"
          footer={
            <button
              type="button"
              onClick={keep}
              dusk="species-custom"
              className="w-full border-t border-border px-3 py-2 text-left text-[13px] text-text-muted hover:bg-surface flex items-center gap-2"
            >
              <Plus size={14} />
              Keep &quot;{common.trim()}&quot; as a custom name
            </button>
          }
          dusk="add-plant-name"
          contentDusk="species-suggestions"
        />
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
      <Field label="Sensors">
        <div className="flex flex-wrap gap-1.5">
          <SensorSelect
            allSensors={allSensors || []}
            selectedSensors={selectedSensors}
            onToggle={toggleSensor}
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
      <FormError message={formError} />
      <FormError message={partialError} />
      <div className="flex justify-end gap-2 pt-1">
        {partialError ? (
          <Button type="button" onClick={onDone}>
            Done
          </Button>
        ) : (
          <TooltipButton
            type="submit"
            dusk="add-plant-submit"
            disabled={create.isPending || !common.trim()}
            tooltipContent={
              create.isPending ? 'Saving...' : !common.trim() ? 'Enter a plant name' : undefined
            }
          >
            <Plus size={16} />
            Add plant
          </TooltipButton>
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
