import { ImageIcon, Plus } from 'lucide-react'
import type { Photo } from '@/api/types'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { EmptyState } from '@/components/app/empty-state'
import { PhotoTile } from '@/components/app/photo-tile'
import { SectionTitle } from '@/components/app/section-title'
import { useCareLog } from '@/components/plant/care-log-context'
import { fmtDate } from '@/lib/format'

interface PlantPhotosCardProps {
  photos: Photo[]
}

export function PlantPhotosCard({ photos }: PlantPhotosCardProps) {
  const { openLog, viewPhoto } = useCareLog()
  return (
    <Card className="p-4">
      <SectionTitle
        icon={ImageIcon}
        action={
          <Button size="sm" variant="ghost" onClick={() => openLog('observation')}>
            <Plus size={14} />
            Add
          </Button>
        }
      >
        Photos
      </SectionTitle>
      {photos.length === 0 ? (
        <EmptyState icon={ImageIcon} title="No photos yet">
          Add a photo when you log an observation.
        </EmptyState>
      ) : (
        <div className="grid grid-cols-3 gap-2">
          {photos.map(ph => (
            <div key={ph.id}>
              <PhotoTile
                photo={ph}
                className="aspect-square w-full"
                onClick={() => viewPhoto(ph)}
              />
              <div className="text-[11px] text-text-subtle mt-1 tnum">{fmtDate(ph.taken_on)}</div>
            </div>
          ))}
        </div>
      )}
    </Card>
  )
}
