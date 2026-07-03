import { useMutation, useQueryClient } from '@tanstack/react-query'
import { createCareEvent, deleteCareEvent, updateCareEvent, uploadPhoto } from '@/api/client'
import type {
  CareEventUpdatePayload,
  FertilizingPayload,
  ObservationPayload,
  PhotoUpload,
  RepottingPayload,
  WateringPayload,
} from '@/api/client'

// One place to wire every care-event write for a plant, so each invalidates the
// timeline (the events feed) and the plant (its server-derived condition) the
// same way.
export function useCareEventMutations(plantId: number) {
  const queryClient = useQueryClient()
  const onSuccess = () => {
    queryClient.invalidateQueries({ queryKey: ['timeline', plantId] })
    queryClient.invalidateQueries({ queryKey: ['plant', plantId] })
    queryClient.invalidateQueries({ queryKey: ['plants'] })
  }

  return {
    createWatering: useMutation({
      mutationFn: (payload: WateringPayload) =>
        createCareEvent(plantId, { type: 'watering', ...payload }),
      onSuccess,
    }),
    createFertilizing: useMutation({
      mutationFn: (payload: FertilizingPayload) =>
        createCareEvent(plantId, { type: 'fertilizing', ...payload }),
      onSuccess,
    }),
    createRepotting: useMutation({
      mutationFn: (payload: RepottingPayload) =>
        createCareEvent(plantId, { type: 'repotting', ...payload }),
      onSuccess,
    }),
    createObservation: useMutation({
      mutationFn: (payload: ObservationPayload) =>
        createCareEvent(plantId, { type: 'observation', ...payload }),
      onSuccess,
    }),
    updateEvent: useMutation({
      mutationFn: ({ eventId, payload }: { eventId: number; payload: CareEventUpdatePayload }) =>
        updateCareEvent(eventId, payload),
      onSuccess,
    }),
    deleteEvent: useMutation({
      mutationFn: (eventId: number) => deleteCareEvent(eventId),
      onSuccess,
    }),
    uploadEventPhoto: useMutation({
      mutationFn: (upload: PhotoUpload) => uploadPhoto(plantId, upload),
      onSuccess,
    }),
  }
}
