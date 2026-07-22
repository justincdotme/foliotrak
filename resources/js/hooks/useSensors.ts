import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  createSensor,
  deleteSensor,
  discoverSensors,
  getSensorCalibration,
  listSensors,
  listSensorTypes,
  testSensorConnection,
  updateSensor,
  updateSensorCalibration,
} from '@/api/client'
import type { SensorPayload, SensorUpdatePayload } from '@/api/client'
import type { Sensor, SensorCalibrationPoint } from '@/api/types'

export function useSensors() {
  const query = useQuery({ queryKey: ['sensors'], queryFn: listSensors })
  return { data: query.data ?? null, loading: query.isPending, error: query.error }
}

export function useSensorTypes() {
  const query = useQuery({ queryKey: ['sensor-types'], queryFn: listSensorTypes })
  return { data: query.data ?? [], loading: query.isPending, error: query.error }
}

// Discovery polls the gateway over the LAN, so it only runs on explicit refetch.
export function useDiscoverSensors() {
  return useQuery({
    queryKey: ['sensors', 'discover'],
    queryFn: discoverSensors,
    enabled: false,
  })
}

export function useTestConnection() {
  return useMutation({ mutationFn: testSensorConnection })
}

export function useCreateSensor() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: SensorPayload) => createSensor(payload),
    onSuccess: (created: Sensor) => {
      qc.setQueryData<Sensor[]>(['sensors'], old => [...(old ?? []), created])
    },
  })
}

export function useUpdateSensor() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: SensorUpdatePayload }) =>
      updateSensor(id, payload),
    onSuccess: (updated: Sensor) => {
      qc.setQueryData<Sensor[]>(['sensors'], old =>
        (old ?? []).map(s => (s.id === updated.id ? updated : s))
      )
      qc.invalidateQueries({ queryKey: ['plants'] })
      qc.invalidateQueries({ queryKey: ['sensor-readings'] })
    },
  })
}

export function useDeleteSensor() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteSensor(id),
    onSuccess: (_data, id) => {
      qc.setQueryData<Sensor[]>(['sensors'], old => (old ?? []).filter(s => s.id !== id))
      qc.invalidateQueries({ queryKey: ['plants'] })
      qc.invalidateQueries({ queryKey: ['sensor-readings'] })
    },
  })
}

export function useSensorCalibration(sensorId: number) {
  return useQuery({
    queryKey: ['sensors', sensorId, 'calibration'],
    queryFn: () => getSensorCalibration(sensorId),
  })
}

export function useUpdateSensorCalibration() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, points }: { id: number; points: SensorCalibrationPoint[] }) =>
      updateSensorCalibration(id, points),
    onSuccess: (calibration, { id }) => {
      qc.setQueryData(['sensors', id, 'calibration'], calibration)
    },
  })
}
