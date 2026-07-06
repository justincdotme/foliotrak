import type { AxiosResponse } from 'axios'
import api from '@/lib/api'
import type {
  CareEvent,
  CropArea,
  DashboardData,
  DiscoveredSensor,
  EquipmentOption,
  FertilizerFormOption,
  GatewayStatus,
  GroupInsights,
  Location,
  LocationSummary,
  NutrientOption,
  Photo,
  PlantRecommendations,
  PlantStatus,
  PlantTimeline,
  PlantWithTags,
  Sensor,
  SensorReadingsResponse,
  SensorSnapshot,
  SensorTypeOption,
  Settings,
  SpeciesSuggestion,
  Symptom,
  Tag,
  User,
  WeightInput,
} from './types'

// API Resources wrap their payload in a `data` envelope.
const unwrap = <T>(res: AxiosResponse<{ data: T }>): T => res.data.data

export interface PlantPayload {
  common_name?: string | null
  scientific_name?: string | null
  nickname?: string | null
  gbif_key?: string | null
  location_id?: number | null
  acquired_on?: string | null
  status?: PlantStatus
  notes?: string | null
  watering_interval_days_override?: number | null
  watering_schedule_start_date?: string | null
  fertilizing_interval_days_override?: number | null
  cover_photo_id?: number | null
  tag_ids?: number[]
  equipment_ids?: number[]
  sensor_ids?: number[]
}

export const listPlants = async (): Promise<PlantWithTags[]> =>
  unwrap(await api.get<{ data: PlantWithTags[] }>('/api/plants'))

export const getPlant = async (id: number): Promise<PlantWithTags> =>
  unwrap(await api.get<{ data: PlantWithTags }>(`/api/plants/${id}`))

export const createPlant = async (payload: PlantPayload): Promise<PlantWithTags> =>
  unwrap(await api.post<{ data: PlantWithTags }>('/api/plants', payload))

export const updatePlant = async (id: number, payload: PlantPayload): Promise<PlantWithTags> =>
  unwrap(await api.patch<{ data: PlantWithTags }>(`/api/plants/${id}`, payload))

export const deletePlant = async (id: number): Promise<void> => {
  await api.delete(`/api/plants/${id}`)
}

export const listTags = async (): Promise<Tag[]> =>
  unwrap(await api.get<{ data: Tag[] }>('/api/tags'))

export const createTag = async (name: string): Promise<Tag> =>
  unwrap(await api.post<{ data: Tag }>('/api/tags', { name }))

export const updateTag = async (id: number, payload: { name?: string }): Promise<Tag> =>
  unwrap(await api.patch<{ data: Tag }>(`/api/tags/${id}`, payload))

export const deleteTag = async (id: number): Promise<void> => {
  await api.delete(`/api/tags/${id}`)
}

export const listPhotos = async (plantId: number): Promise<Photo[]> =>
  unwrap(await api.get<{ data: Photo[] }>(`/api/plants/${plantId}/photos`))

export interface PhotoUpload {
  file: File
  caption?: string | null
  takenOn?: string | null
  setAsCover?: boolean
  careEventId?: number | null
  heroCrop?: CropArea | null
  thumbCrop?: CropArea | null
}

export const uploadPhoto = async (plantId: number, upload: PhotoUpload): Promise<Photo> => {
  const form = new FormData()
  form.append('photo', upload.file)
  if (upload.caption) form.append('caption', upload.caption)
  if (upload.takenOn) form.append('taken_on', upload.takenOn)
  if (upload.setAsCover) form.append('set_as_cover', '1')
  if (upload.careEventId != null) form.append('care_event_id', String(upload.careEventId))
  if (upload.heroCrop) form.append('hero_crop', JSON.stringify(upload.heroCrop))
  if (upload.thumbCrop) form.append('thumb_crop', JSON.stringify(upload.thumbCrop))

  return unwrap(await api.post<{ data: Photo }>(`/api/plants/${plantId}/photos`, form))
}

export const setCoverPhoto = (plantId: number, photoId: number | null): Promise<PlantWithTags> =>
  updatePlant(plantId, { cover_photo_id: photoId })

export const deletePhoto = async (photoId: number): Promise<void> => {
  await api.delete(`/api/photos/${photoId}`)
}

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
  ambient_humidity_pct?: number | null
  ambient_temp?: number | null
  soil_moisture_relative?: 'dry' | 'moist' | 'wet' | null
  soil_moisture_precise?: number | null
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
      to_location_id: number | null
      from_location_id: number | null
    }
>

export type CareEventCreatePayload =
  | ({ type: 'watering' } & WateringPayload)
  | ({ type: 'fertilizing' } & FertilizingPayload)
  | ({ type: 'repotting' } & RepottingPayload)
  | ({ type: 'observation' } & ObservationPayload)

export const createCareEvent = async (
  plantId: number,
  payload: CareEventCreatePayload
): Promise<CareEvent> =>
  unwrap(await api.post<{ data: CareEvent }>(`/api/plants/${plantId}/care-events`, payload))

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

export const getRecommendations = async (plantId: number): Promise<PlantRecommendations> =>
  unwrap(await api.get<{ data: PlantRecommendations }>(`/api/plants/${plantId}/recommendations`))

export const getDashboard = async (): Promise<DashboardData> =>
  unwrap(await api.get<{ data: DashboardData }>('/api/dashboard'))

export const getGroupInsights = async (params: {
  tag?: number
  location?: number
}): Promise<GroupInsights> => {
  const query = new URLSearchParams()
  if (params.tag) query.set('tag', String(params.tag))
  if (params.location) query.set('location', String(params.location))
  return unwrap(await api.get<{ data: GroupInsights }>(`/api/insights/group?${query}`))
}

export const getLocationSummary = async (): Promise<LocationSummary[]> =>
  unwrap(await api.get<{ data: LocationSummary[] }>('/api/insights/locations'))

export const listSymptoms = async (): Promise<Symptom[]> =>
  unwrap(await api.get<{ data: Symptom[] }>('/api/symptoms'))

export const listNutrients = async (): Promise<NutrientOption[]> =>
  unwrap(await api.get<{ data: NutrientOption[] }>('/api/nutrients'))

export const listFertilizerForms = async (): Promise<FertilizerFormOption[]> =>
  unwrap(await api.get<{ data: FertilizerFormOption[] }>('/api/fertilizer-forms'))

export const listEquipment = async (): Promise<EquipmentOption[]> =>
  unwrap(await api.get<{ data: EquipmentOption[] }>('/api/equipment'))

export const createEquipment = async (label: string): Promise<EquipmentOption> =>
  unwrap(await api.post<{ data: EquipmentOption }>('/api/equipment', { label }))

export const updateEquipment = async (
  id: number,
  payload: { label?: string }
): Promise<EquipmentOption> =>
  unwrap(await api.patch<{ data: EquipmentOption }>(`/api/equipment/${id}`, payload))

export const deleteEquipment = async (id: number): Promise<void> => {
  await api.delete(`/api/equipment/${id}`)
}

// The /api/user route returns the model directly, not the `data` envelope the
// API Resources use, so this one is read without unwrap.
export const getUser = async (): Promise<User> => (await api.get<User>('/api/user')).data

export interface SettingsPayload {
  pushover_user_key?: string | null
}

export const getSettings = async (): Promise<Settings> =>
  unwrap(await api.get<{ data: Settings }>('/api/settings'))

export const updateSettings = async (payload: SettingsPayload): Promise<Settings> =>
  unwrap(await api.patch<{ data: Settings }>('/api/settings', payload))

export const listLocations = async (): Promise<Location[]> =>
  unwrap(await api.get<{ data: Location[] }>('/api/locations'))

export const createLocation = async (name: string): Promise<Location> =>
  unwrap(await api.post<{ data: Location }>('/api/locations', { name }))

export const listSensorTypes = async (): Promise<SensorTypeOption[]> =>
  unwrap(await api.get<{ data: SensorTypeOption[] }>('/api/sensor-types'))

export interface SensorPayload {
  mac: string
  device_name?: string | null
  name: string
  location?: string | null
  type: string
}

export interface SensorUpdatePayload {
  name?: string
  color?: string
  location?: string | null
}

export const listSensors = async (): Promise<Sensor[]> =>
  unwrap(await api.get<{ data: Sensor[] }>('/api/sensors'))

export const createSensor = async (payload: SensorPayload): Promise<Sensor> =>
  unwrap(await api.post<{ data: Sensor }>('/api/sensors', payload))

export const updateSensor = async (id: number, payload: SensorUpdatePayload): Promise<Sensor> =>
  unwrap(await api.patch<{ data: Sensor }>(`/api/sensors/${id}`, payload))

export const deleteSensor = async (id: number): Promise<void> => {
  await api.delete(`/api/sensors/${id}`)
}

// Discovery reports gateway failures as a sibling `error` key on a 200, so the
// raw body is returned instead of unwrapping `data`.
export const discoverSensors = async (): Promise<{ data: DiscoveredSensor[]; error?: string }> =>
  (await api.get<{ data: DiscoveredSensor[]; error?: string }>('/api/sensors/discover')).data

export const testSensorConnection = async (): Promise<GatewayStatus> =>
  unwrap(await api.post<{ data: GatewayStatus }>('/api/sensors/test-connection'))

export const getPlantSensorReadings = async (
  plantId: number,
  range: 'day' | 'week' | 'month'
): Promise<SensorReadingsResponse> =>
  unwrap(
    await api.get<{ data: SensorReadingsResponse }>(`/api/plants/${plantId}/sensor-readings`, {
      params: { range },
    })
  )

export const fetchSensorSnapshot = async (
  plantId: number,
  at?: string
): Promise<SensorSnapshot | null> => {
  const res = await api.get(`/api/plants/${plantId}/sensor-snapshot`, {
    params: at ? { at } : undefined,
  })
  if (res.status === 204) return null
  return (res.data as { data: SensorSnapshot }).data
}
