import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { PhotoUpload, PlantPayload } from '@/api/client'
import { createPlant, setCoverPhoto, updatePlant, uploadPhoto } from '@/api/client'

export interface CreatePlantInput {
  payload: PlantPayload
  coverFile?: File | null
}

// Quick-add can attach a first photo; uploading it as the cover in the same flow
// means the new card carries an image immediately.
export function useCreatePlant() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ payload, coverFile }: CreatePlantInput) => {
      const plant = await createPlant(payload)
      if (coverFile) {
        try {
          await uploadPhoto(plant.id, {
            file: coverFile,
            caption: 'Cover photo',
            setAsCover: true,
          })
        } catch {
          // The plant is already created, so a failed optional cover upload must
          // not lose it; the cover can be set later from the detail page.
        }
      }
      return plant
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
    onSuccess: () => {
      // The ['plant', id] prefix also covers the plant's photos query.
      queryClient.invalidateQueries({ queryKey: ['plant', plantId] })
      queryClient.invalidateQueries({ queryKey: ['plants'] })
      // A location change is logged server-side as a relocation care event.
      queryClient.invalidateQueries({ queryKey: ['timeline', plantId] })
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
