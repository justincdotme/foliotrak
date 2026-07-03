import { ChevronRight, Pencil, Trash2 } from 'lucide-react'
import { useState } from 'react'
import type { CareEvent, Photo } from '@/api/types'
import { Button } from '@/components/ui/button'
import { ConfirmDelete } from '@/components/app/confirm-delete'
import { PhotoTile } from '@/components/app/photo-tile'
import { CARE_META } from '@/lib/domain'
import { fmtDate, fmtDateY, fmtTime } from '@/lib/format'
import { cn } from '@/lib/utils'
import { EventDetail } from './event-detail'

interface TimelineItemProps {
  e: CareEvent
  photos: Photo[]
  onEdit: () => void
  onViewPhoto: (photo: Photo) => void
  onDelete: (id: number) => Promise<void>
}

export function TimelineItem({ e, photos, onEdit, onViewPhoto, onDelete }: TimelineItemProps) {
  const [open, setOpen] = useState(false)
  const [confirm, setConfirm] = useState(false)
  const m = CARE_META[e.type]

  return (
    <div className="relative pl-9">
      <div className="absolute left-[10px] top-1 bottom-0 w-px bg-border" />
      <span
        className="absolute left-0 top-1 w-[22px] h-[22px] rounded-full grid place-items-center"
        style={{ background: `color-mix(in srgb,${m.color} 16%,transparent)`, color: m.color }}
      >
        <m.icon size={13} />
      </span>
      <div className="pb-5">
        <button
          onClick={() => setOpen(o => !o)}
          dusk="timeline-item"
          className="w-full text-left flex items-center gap-2"
        >
          <span className="font-medium">{m.label}</span>
          <span className="text-[12px] text-text-subtle tnum">
            {fmtDate(e.occurred_at)} · {fmtTime(e.occurred_at)}
          </span>
          <ChevronRight
            size={14}
            className={cn('ml-auto text-text-subtle transition-transform', open && 'rotate-90')}
          />
        </button>
        {e.note && !open && (
          <div className="text-[12px] text-text-muted truncate mt-0.5">{e.note}</div>
        )}
        {open && (
          <div className="mt-2 rounded-[8px] border border-border bg-surface-raised p-3">
            <EventDetail e={e} />
            {photos.length > 0 && (
              <div className="flex flex-wrap gap-2 mt-2">
                {photos.map(p => (
                  <PhotoTile
                    key={p.id}
                    photo={p}
                    className="h-16 w-16 rounded-[6px]"
                    onClick={() => onViewPhoto(p)}
                  />
                ))}
              </div>
            )}
            {e.note && (
              <div className="text-[12px] text-text-muted mt-2 pt-2 border-t border-border">
                {e.note}
              </div>
            )}
            <div className="flex gap-1 mt-2.5 pt-2.5 border-t border-border">
              <Button size="sm" variant="ghost" dusk="timeline-edit" onClick={onEdit}>
                <Pencil size={14} />
                Edit
              </Button>
              <Button
                size="sm"
                variant="ghost"
                className="text-overdue"
                dusk="timeline-delete"
                onClick={() => setConfirm(true)}
              >
                <Trash2 size={14} />
                Delete
              </Button>
            </div>
          </div>
        )}
      </div>
      <ConfirmDelete
        open={confirm}
        onClose={() => setConfirm(false)}
        onConfirm={() => onDelete(e.id)}
        label={`This ${m.label.toLowerCase()} entry from ${fmtDateY(e.occurred_at)} will be removed.`}
      />
    </div>
  )
}
