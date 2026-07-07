import { Clock } from 'lucide-react'
import { useMemo } from 'react'
import type { CareEvent, Photo } from '@/api/types'
import { Card } from '@/components/ui/card'
import { EmptyState } from '@/components/app/empty-state'
import { SectionTitle } from '@/components/app/section-title'
import { TimelineItem } from '@/components/plant/timeline-item'
import { useCareEventMutations } from '@/hooks/useCareEventMutations'
import { useNotification } from '@/components/app/notification-context'
import { handleApiError } from '@/lib/handle-api-error'
import { groupPhotosByCareEvent } from '@/lib/photos'

interface PlantCareTimelineProps {
  plantId: number
  events: CareEvent[]
  photos: Photo[]
}

export function PlantCareTimeline({ plantId, events, photos }: PlantCareTimelineProps) {
  const { deleteEvent } = useCareEventMutations(plantId)
  const { showError } = useNotification()
  const photosByEvent = useMemo(() => groupPhotosByCareEvent(photos), [photos])

  return (
    <Card dusk="care-timeline" className="p-4">
      <SectionTitle icon={Clock}>Care timeline</SectionTitle>
      {events.length === 0 ? (
        <div dusk="timeline-empty">
          <EmptyState icon={Clock} title="No care events logged yet">
            Start by logging a watering or observation.
          </EmptyState>
        </div>
      ) : (
        <div className="mt-1">
          {events.map(e => (
            <TimelineItem
              key={e.id}
              e={e}
              photos={photosByEvent[e.id] ?? []}
              onDelete={async () => {
                try {
                  await deleteEvent.mutateAsync(e.id)
                } catch (err) {
                  showError(handleApiError(err))
                }
              }}
            />
          ))}
        </div>
      )}
    </Card>
  )
}
