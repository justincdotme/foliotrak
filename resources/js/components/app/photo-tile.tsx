import { cn } from '@/lib/utils'
import { photoUrl } from '@/lib/photos'

interface PhotoTileProps {
  photo?: { path?: string | null; caption?: string | null } | null
  className?: string
  onClick?: () => void
}

export function PhotoTile({ photo, className = '', onClick }: PhotoTileProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'relative overflow-hidden rounded-md border border-border bg-surface-raised group',
        className
      )}
    >
      {photo?.path ? (
        <img
          src={photoUrl(photo.path)}
          alt={photo?.caption || 'Plant photo'}
          loading="lazy"
          className="absolute inset-0 h-full w-full object-cover"
        />
      ) : (
        <div className="absolute inset-0 h-full w-full plant-silhouette-thumb" />
      )}
      {photo?.caption && (
        <div className="absolute inset-x-0 bottom-0 p-1.5 text-[11px] text-left text-white bg-gradient-to-t from-black/55 to-transparent">
          {photo.caption}
        </div>
      )}
    </button>
  )
}
