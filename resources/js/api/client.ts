import type { AxiosResponse } from 'axios'
import api from '@/lib/api'
import type { Photo, PlantStatus, PlantWithTags, SpeciesSuggestion, Tag } from './types'

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
}

export const uploadPhoto = async (plantId: number, upload: PhotoUpload): Promise<Photo> => {
  const form = new FormData()
  form.append('photo', upload.file)
  if (upload.caption) form.append('caption', upload.caption)
  if (upload.takenOn) form.append('taken_on', upload.takenOn)
  if (upload.setAsCover) form.append('set_as_cover', '1')

  return unwrap(await api.post<{ data: Photo }>(`/api/plants/${plantId}/photos`, form))
}

export const setCoverPhoto = (plantId: number, photoId: number | null): Promise<PlantWithTags> =>
  updatePlant(plantId, { cover_photo_id: photoId })

export const suggestSpecies = async (q: string, limit = 8): Promise<SpeciesSuggestion[]> =>
  unwrap(
    await api.get<{ data: SpeciesSuggestion[] }>('/api/species/suggest', { params: { q, limit } })
  )
