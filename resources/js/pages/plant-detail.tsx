import { ChevronLeft, Sprout } from 'lucide-react'
import { useState } from 'react'
import type { PlantTimeline } from '@/api/types'
import { usePlant } from '@/hooks/usePlant'
import { usePlantPhotos } from '@/hooks/usePlantPhotos'
import { useTimeline } from '@/hooks/useTimeline'
import { useRecommendations } from '@/hooks/useRecommendations'
import { Card } from '@/components/ui/card'
import { EmptyState } from '@/components/app/empty-state'
import { Spinner } from '@/components/app/spinner'
import { EditPlantModal } from '@/components/plant/edit-plant-modal'
import { PrimaryPhotoModal } from '@/components/plant/primary-photo-modal'
import { ScheduleSection, type NextDue } from '@/components/plant/schedule-section'
import { PlantHeader } from '@/components/plant/plant-header'
import { PlantLogActions } from '@/components/plant/plant-log-actions'
import { PlantEquipmentCard } from '@/components/plant/plant-equipment-card'
import { PlantSensorsCard } from '@/components/plant/plant-sensors-card'
import { PlantChartsPanel } from '@/components/plant/plant-charts-panel'
import { PlantPhotosCard } from '@/components/plant/plant-photos-card'
import { PlantCareTimeline } from '@/components/plant/plant-care-timeline'

interface PlantDetailPageProps {
  id: number
  go: (to: string) => void
}

function wateringDue(timeline: PlantTimeline | null): NextDue {
  const entry = timeline?.due_for_care?.find(d => d.type === 'watering')
  if (!entry) return null
  return {
    due_date: entry.due_date,
    daysLeft: entry.daysLeft,
    status: entry.status,
    type: 'watering',
    interval: entry.interval,
    last_watered: entry.due_date,
  }
}

export function PlantDetailPage({ id, go }: PlantDetailPageProps) {
  const { data: plant, loading } = usePlant(id)
  const { data: photos } = usePlantPhotos(id)
  const { data: timeline } = useTimeline(id)
  const {
    data: recommendations,
    loading: recommendationsLoading,
    error: recommendationsError,
  } = useRecommendations(id)
  const [editOpen, setEditOpen] = useState(false)
  const [photoOpen, setPhotoOpen] = useState(false)

  if (loading) return <Spinner />

  if (!plant)
    return (
      <Card>
        <EmptyState icon={Sprout} title="Plant not found">
          It may have been deleted.
        </EmptyState>
      </Card>
    )

  const photoList = photos ?? []

  return (
    <div className="space-y-6">
      <button
        onClick={() => go('/plants')}
        className="inline-flex items-center gap-1 text-[13px] text-text-muted hover:text-text"
      >
        <ChevronLeft size={16} />
        All plants
      </button>

      <PlantHeader
        plant={plant}
        onEdit={() => setEditOpen(true)}
        onChangeCover={() => setPhotoOpen(true)}
      />

      {plant.notes && <p className="text-[13px] text-text-muted">{plant.notes}</p>}

      <PlantLogActions />

      <PlantEquipmentCard plant={plant} />

      <PlantSensorsCard plant={plant} />

      <ScheduleSection
        plant={plant}
        recommendations={recommendations}
        recommendationsLoading={recommendationsLoading}
        recommendationsError={!!recommendationsError}
        due={wateringDue(timeline)}
      />

      <EditPlantModal plant={plant} open={editOpen} onClose={() => setEditOpen(false)} />
      <PrimaryPhotoModal
        plant={plant}
        photos={photoList}
        open={photoOpen}
        onClose={() => setPhotoOpen(false)}
      />

      <PlantChartsPanel timeline={timeline} recommendations={recommendations} />

      <PlantPhotosCard photos={photoList} />

      <PlantCareTimeline plantId={id} events={timeline?.events ?? []} photos={photoList} />
    </div>
  )
}
