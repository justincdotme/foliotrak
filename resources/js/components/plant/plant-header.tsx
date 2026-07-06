import { Calendar, Camera, ExternalLink, MapPin, Pencil, Trash2 } from 'lucide-react'
import type { PlantWithTags } from '@/api/types'
import { Chip } from '@/components/app/chip'
import { ConditionChip } from '@/components/app/condition-chip'
import { IconButton } from '@/components/app/icon-button'
import { StatusPill } from '@/components/app/status-pill'
import { fmtDateY, formatSensorLabel } from '@/lib/format'
import { cn } from '@/lib/utils'
import { photoUrl } from '@/lib/photos'

interface PlantHeaderProps {
  plant: PlantWithTags
  onEdit: () => void
  onChangeCover: () => void
  onDelete: () => void
}

export function PlantHeader({ plant, onEdit, onChangeCover, onDelete }: PlantHeaderProps) {
  return (
    <div className="flex gap-4 items-start">
      <div className="relative shrink-0">
        <div className="w-[120px] h-[180px] rounded-[10px] border border-border overflow-hidden bg-surface-raised">
          <img
            src={
              plant.cover_photo
                ? photoUrl(plant.cover_photo.path)
                : '/images/plant-silhouette-hero.png'
            }
            alt=""
            dusk="cover-hero"
            className={cn('h-full w-full object-cover', !plant.cover_photo && 'opacity-20')}
          />
        </div>
        <button
          onClick={onChangeCover}
          aria-label="Change cover photo"
          dusk="change-cover"
          title="Change cover photo"
          className="absolute -bottom-1.5 -right-1.5 w-7 h-7 rounded-full bg-surface border border-border-strong grid place-items-center text-text-muted hover:text-text shadow-sm"
        >
          <Camera size={14} />
        </button>
      </div>
      <div className="min-w-0 flex-1">
        <div className="flex items-start gap-2">
          <div className="min-w-0">
            <h1 className="text-xl font-semibold truncate">{plant.common_name}</h1>
            {plant.nickname && (
              <div className="text-[13px] text-text-muted truncate">{plant.nickname}</div>
            )}
            <div className="italic text-text-muted text-[13px] truncate">
              {plant.scientific_name}
            </div>
          </div>
          <div className="ml-auto flex gap-1 shrink-0">
            <IconButton label="Edit plant" onClick={onEdit} className="h-9 w-9">
              <Pencil size={15} />
            </IconButton>
            <IconButton label="Delete plant" onClick={onDelete} className="h-9 w-9">
              <Trash2 size={15} />
            </IconButton>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2 text-[12px] text-text-muted">
          <span className="flex items-center gap-1">
            <MapPin size={12} />
            {plant.location?.name || 'No location'}
          </span>
          {plant.acquired_on && (
            <span className="flex items-center gap-1">
              <Calendar size={12} />
              Since {fmtDateY(plant.acquired_on)}
            </span>
          )}
          {plant.gbif_key && (
            <a
              href={`https://www.gbif.org/species/${plant.gbif_key}`}
              target="_blank"
              rel="noreferrer"
              className="flex items-center gap-1 text-primary hover:underline"
            >
              GBIF <ExternalLink size={11} />
            </a>
          )}
        </div>
        <div className="flex flex-wrap gap-1.5 mt-2">
          <ConditionChip cond={plant.condition} />
          {plant.status !== 'active' && <StatusPill status={plant.status} />}
          {plant.tags.map(t => (
            <Chip key={t.id} color={t.color || undefined}>
              {t.name}
            </Chip>
          ))}
        </div>
        {plant.sensors && plant.sensors.length > 0 && (
          <div className="flex flex-wrap gap-1.5 mt-2">
            {plant.sensors.map(s => (
              <Chip key={s.id} color={s.color || undefined}>
                {formatSensorLabel(s.name, s.location)}
              </Chip>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
