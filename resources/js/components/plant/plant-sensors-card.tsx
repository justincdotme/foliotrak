import { Radio } from 'lucide-react'
import type { PlantSensor, PlantWithTags } from '@/api/types'
import { Card } from '@/components/ui/card'
import { SectionTitle } from '@/components/app/section-title'
import { SensorSelect } from '@/components/app/sensor-select'
import { useSensors } from '@/hooks/useSensors'
import { useUpdatePlant } from '@/hooks/usePlantMutations'
import { useNotification } from '@/components/app/notification-context'
import { handleApiError } from '@/lib/handle-api-error'

interface PlantSensorsCardProps {
  plant: PlantWithTags
}

export function PlantSensorsCard({ plant }: PlantSensorsCardProps) {
  const { data: allSensors } = useSensors()
  const update = useUpdatePlant(plant.id)
  const { showError } = useNotification()

  const handleToggle = async (sensor: PlantSensor) => {
    const current = plant.sensors?.map(s => s.id) ?? []
    const next = current.includes(sensor.id)
      ? current.filter(id => id !== sensor.id)
      : [...current, sensor.id]
    try {
      await update.mutateAsync({ sensor_ids: next })
    } catch (err) {
      showError(handleApiError(err))
    }
  }

  return (
    <Card className="p-4">
      <SectionTitle icon={Radio}>Sensors</SectionTitle>
      <div className="mt-2 flex flex-wrap gap-1.5">
        <SensorSelect
          allSensors={allSensors ?? []}
          selectedSensors={plant.sensors ?? []}
          onToggle={handleToggle}
        />
      </div>
    </Card>
  )
}
