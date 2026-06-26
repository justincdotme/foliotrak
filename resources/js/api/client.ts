import type { AxiosResponse } from 'axios'
import api from '@/lib/api'
import type {
  CareEvent,
  FertilizerFormOption,
  NutrientOption,
  Photo,
  PlantStatus,
  PlantTimeline,
  PlantWithTags,
  SpeciesSuggestion,
  Symptom,
  Tag,
  WeightInput,
} from './types'

// API Resources wrap their payload in a `data` envelope.
const unwrap = <T>(res: AxiosResponse<{ data: T }>): T => res.data.data

export interface PlantPayload {
  common_name?: string | null
  scientific_name?: string | null
  gbif_key?: string | null
  location?: string | null
  acquired_on?: string | null
  status?: PlantStatus
  notes?: string | null
  watering_interval_days_override?: number | null
  fertilizing_interval_days_override?: number | null
  cover_photo_id?: number | null
  tag_ids?: number[]
}

export const listPlants = async (): Promise<PlantWithTags[]> =>
  unwrap(await api.get<{ data: PlantWithTags[] }>('/api/plants'))

export const getPlant = async (id: number): Promise<PlantWithTags> =>
  unwrap(await api.get<{ data: PlantWithTags }>(`/api/plants/${id}`))

export const createPlant = async (payload: PlantPayload): Promise<PlantWithTags> =>
  unwrap(await api.post<{ data: PlantWithTags }>('/api/plants', payload))

export const updatePlant = async (id: number, payload: PlantPayload): Promise<PlantWithTags> =>
  unwrap(await api.patch<{ data: PlantWithTags }>(`/api/plants/${id}`, payload))

export const listTags = async (): Promise<Tag[]> =>
  unwrap(await api.get<{ data: Tag[] }>('/api/tags'))

export const listPhotos = async (plantId: number): Promise<Photo[]> =>
  unwrap(await api.get<{ data: Photo[] }>(`/api/plants/${plantId}/photos`))

export interface PhotoUpload {
  file: File
  caption?: string | null
  takenOn?: string | null
  setAsCover?: boolean
  careEventId?: number | null
}

export const uploadPhoto = async (plantId: number, upload: PhotoUpload): Promise<Photo> => {
  const form = new FormData()
  form.append('photo', upload.file)
  if (upload.caption) form.append('caption', upload.caption)
  if (upload.takenOn) form.append('taken_on', upload.takenOn)
  if (upload.setAsCover) form.append('set_as_cover', '1')
  if (upload.careEventId != null) form.append('care_event_id', String(upload.careEventId))

  return unwrap(await api.post<{ data: Photo }>(`/api/plants/${plantId}/photos`, form))
}

export const setCoverPhoto = (plantId: number, photoId: number | null): Promise<PlantWithTags> =>
  updatePlant(plantId, { cover_photo_id: photoId })

export const suggestSpecies = async (q: string, limit = 8): Promise<SpeciesSuggestion[]> =>
  unwrap(
    await api.get<{ data: SpeciesSuggestion[] }>('/api/species/suggest', { params: { q, limit } })
  )

export interface NutrientInput {
  nutrient_id: number
  note?: string | null
}

export interface WateringPayload {
  occurred_at: string
  amount_ml?: number | null
  note?: string | null
}

export interface FertilizingPayload {
  occurred_at: string
  fertilizer_form_id: number
  brand?: string | null
  product?: string | null
  npk_n?: number | null
  npk_p?: number | null
  npk_k?: number | null
  dose_pct?: number | null
  amount_ml?: number | null
  nutrients?: NutrientInput[]
  note?: string | null
}

export interface RepottingPayload {
  occurred_at: string
  soil_recipe?: string | null
  pot_size_value?: number | null
  pot_size_unit?: 'in' | 'cm'
  fertilizer_added?: boolean
  note?: string | null
}

export interface ObservationPayload {
  occurred_at: string
  overall_health?: number | null
  health_note?: string | null
  light_level?: number | null
  growth_rate?: 'none' | 'slow' | 'moderate' | 'fast' | null
  growth_note?: string | null
  leaf_size_mm?: number | null
  weight?: WeightInput | null
  symptom_ids?: number[]
  custom_symptoms?: string[]
  note?: string | null
}

// PATCH accepts the union of every type's fields and applies only those relevant
// to the event's own type, so an edit form sends its own subset.
export type CareEventUpdatePayload = Partial<
  Omit<WateringPayload, 'occurred_at'> &
    Omit<FertilizingPayload, 'occurred_at'> &
    Omit<RepottingPayload, 'occurred_at'> &
    Omit<ObservationPayload, 'occurred_at'> & {
      occurred_at: string
      to_location: string | null
      from_location: string | null
    }
>

export const createWatering = async (
  plantId: number,
  payload: WateringPayload
): Promise<CareEvent> =>
  unwrap(await api.post<{ data: CareEvent }>(`/api/plants/${plantId}/waterings`, payload))

export const createFertilizing = async (
  plantId: number,
  payload: FertilizingPayload
): Promise<CareEvent> =>
  unwrap(await api.post<{ data: CareEvent }>(`/api/plants/${plantId}/fertilizings`, payload))

export const createRepotting = async (
  plantId: number,
  payload: RepottingPayload
): Promise<CareEvent> =>
  unwrap(await api.post<{ data: CareEvent }>(`/api/plants/${plantId}/repottings`, payload))

export const createObservation = async (
  plantId: number,
  payload: ObservationPayload
): Promise<CareEvent> =>
  unwrap(await api.post<{ data: CareEvent }>(`/api/plants/${plantId}/observations`, payload))

export const updateCareEvent = async (
  eventId: number,
  payload: CareEventUpdatePayload
): Promise<CareEvent> =>
  unwrap(await api.patch<{ data: CareEvent }>(`/api/care-events/${eventId}`, payload))

export const deleteCareEvent = async (eventId: number): Promise<void> => {
  await api.delete(`/api/care-events/${eventId}`)
}

export const getTimeline = async (plantId: number): Promise<PlantTimeline> =>
  unwrap(await api.get<{ data: PlantTimeline }>(`/api/plants/${plantId}/timeline`))

export const listSymptoms = async (): Promise<Symptom[]> =>
  unwrap(await api.get<{ data: Symptom[] }>('/api/symptoms'))

export const listNutrients = async (): Promise<NutrientOption[]> =>
  unwrap(await api.get<{ data: NutrientOption[] }>('/api/nutrients'))

export const listFertilizerForms = async (): Promise<FertilizerFormOption[]> =>
  unwrap(await api.get<{ data: FertilizerFormOption[] }>('/api/fertilizer-forms'))
