import { Settings2 } from 'lucide-react'
import type { PlantWithTags } from '@/api/types'
import { Card } from '@/components/ui/card'
import { Chip } from '@/components/app/chip'
import { SectionTitle } from '@/components/app/section-title'
import { useEquipment } from '@/hooks/useEquipment'
import { useUpdatePlant } from '@/hooks/usePlantMutations'
import { useNotification } from '@/components/app/notification-context'
import { handleApiError } from '@/lib/handle-api-error'

interface PlantEquipmentCardProps {
  plant: PlantWithTags
}

export function PlantEquipmentCard({ plant }: PlantEquipmentCardProps) {
  const { data: allEquipment } = useEquipment()
  const update = useUpdatePlant(plant.id)
  const { showError } = useNotification()

  return (
    <Card className="p-4">
      <SectionTitle icon={Settings2}>Equipment</SectionTitle>
      <div className="mt-2 flex flex-wrap gap-1.5">
        {allEquipment.map(eq => {
          const active = plant.equipment?.some(e => e.id === eq.id) ?? false
          return (
            <Chip
              key={eq.id}
              active={active}
              outline={!active}
              color="var(--primary)"
              onClick={async () => {
                const current = plant.equipment?.map(e => e.id) ?? []
                const next = active ? current.filter(x => x !== eq.id) : [...current, eq.id]
                try {
                  await update.mutateAsync({ equipment_ids: next })
                } catch (err) {
                  showError(handleApiError(err))
                }
              }}
            >
              {eq.label}
            </Chip>
          )
        })}
      </div>
      {allEquipment.length === 0 && (
        <p className="text-[13px] text-text-muted mt-1">No equipment options available.</p>
      )}
    </Card>
  )
}
