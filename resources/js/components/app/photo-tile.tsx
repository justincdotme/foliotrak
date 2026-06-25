import { Leaf } from 'lucide-react'
import { cn } from '@/lib/utils'

interface PhotoTileProps {
  photo?: { caption?: string | null } | null
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
      style={{
        backgroundImage:
          'repeating-linear-gradient(135deg, color-mix(in srgb, var(--primary) 8%, transparent) 0 10px, transparent 10px 20px)',
      }}
    >
      <div className="absolute inset-0 grid place-items-center text-text-subtle">
        <Leaf size={20} />
      </div>
      {photo?.caption && (
        <div className="absolute inset-x-0 bottom-0 p-1.5 text-[11px] text-left text-white bg-gradient-to-t from-black/55 to-transparent">
          {photo.caption}
        </div>
      )}
    </button>
  )
}
