import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { CropArea, PlantWithTags } from '@/api/types'
import type { PhotoUpload, PlantPayload } from '@/api/client'
import { createPlant, deletePhoto, setCoverPhoto, updatePlant, uploadPhoto } from '@/api/client'
import { plantInvalidationKeys } from '@/lib/invalidation'

export interface CreatePlantInput {
  payload: PlantPayload
  cover?: { file: File; heroCrop: CropArea; thumbCrop: CropArea } | null
}

export interface CreatePlantResult {
  plant: PlantWithTags
  coverUploadFailed: boolean
}

// Quick-add can attach a first photo; it ships with both crop areas because the
// API rejects an uncropped cover. A failed upload must not lose the created
// plant, so the failure is returned as a flag instead of thrown.
export function useCreatePlant() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ payload, cover }: CreatePlantInput): Promise<CreatePlantResult> => {
      const plant = await createPlant(payload)
      let coverUploadFailed = false
      if (cover) {
        try {
          await uploadPhoto(plant.id, {
            file: cover.file,
            caption: 'Cover photo',
            setAsCover: true,
            heroCrop: cover.heroCrop,
            thumbCrop: cover.thumbCrop,
          })
        } catch {
          coverUploadFailed = true
        }
      }
      return { plant, coverUploadFailed }
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['plants'] })
    },
  })
}

export function useUpdatePlant(plantId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: PlantPayload) => updatePlant(plantId, payload),
    onSuccess: updatedPlant => {
      queryClient.setQueryData<PlantWithTags[]>(['plants'], old =>
        old?.map(p => (p.id === updatedPlant.id ? updatedPlant : p))
      )
      plantInvalidationKeys(plantId).forEach(queryKey =>
        queryClient.invalidateQueries({ queryKey })
      )
    },
  })
}

export function useUploadPhoto(plantId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (upload: PhotoUpload) => uploadPhoto(plantId, upload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['plant', plantId] })
      queryClient.invalidateQueries({ queryKey: ['plants'] })
    },
  })
}

export function useSetCoverPhoto(plantId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (photoId: number | null) => setCoverPhoto(plantId, photoId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['plant', plantId] })
      queryClient.invalidateQueries({ queryKey: ['plants'] })
    },
  })
}

export function useDeletePhoto(plantId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (photoId: number) => deletePhoto(photoId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['plant', plantId] })
      queryClient.invalidateQueries({ queryKey: ['plants'] })
    },
  })
}
