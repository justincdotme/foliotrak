import { Camera, Check, Upload } from 'lucide-react'
import { type ChangeEvent } from 'react'
import type { Photo, Plant } from '@/api/types'
import { Button } from '@/components/ui/button'
import { Modal } from '@/components/app/modal'
import { cn } from '@/lib/utils'
import { photoUrl } from '@/lib/photos'
import { useSetCoverPhoto, useUploadPhoto } from '@/hooks/usePlantMutations'

interface PrimaryPhotoModalProps {
  plant: Plant
  photos: Photo[]
  open: boolean
  onClose: () => void
}

export function PrimaryPhotoModal({ plant, photos, open, onClose }: PrimaryPhotoModalProps) {
  const upload = useUploadPhoto(plant.id)
  const setCover = useSetCoverPhoto(plant.id)
  const busy = upload.isPending || setCover.isPending
  const failed = upload.isError || setCover.isError

  const onUpload = async (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    try {
      await upload.mutateAsync({ file, caption: 'Cover photo', setAsCover: true })
      onClose()
    } catch {
      // The failure line below covers the error; the input stays for a retry.
    }
  }

  const pick = async (photoId: number) => {
    try {
      await setCover.mutateAsync(photoId)
      onClose()
    } catch {
      // The failure line below covers the error.
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Cover photo"
      subtitle="The card image, separate from logged photos."
      footer={
        <Button variant="ghost" onClick={onClose}>
          Done
        </Button>
      }
    >
      <div className="space-y-4">
        {photos.length > 0 && (
          <div>
            <div className="mb-2 text-[12px] text-text-muted">Pick from photos on this plant</div>
            <div className="grid grid-cols-3 gap-2">
              {photos.map(ph => {
                const isCover = plant.cover_photo_id === ph.id
                return (
                  <button
                    key={ph.id}
                    type="button"
                    onClick={() => pick(ph.id)}
                    disabled={busy}
                    aria-pressed={isCover}
                    className={cn(
                      'relative aspect-square overflow-hidden rounded-md border',
                      isCover ? 'border-primary ring-2 ring-primary' : 'border-border'
                    )}
                  >
                    <img
                      src={photoUrl(ph.path)}
                      alt={ph.caption || 'Plant photo'}
                      className="h-full w-full object-cover"
                    />
                    {isCover && (
                      <span className="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-primary text-white">
                        <Check size={12} />
                      </span>
                    )}
                  </button>
                )
              })}
            </div>
          </div>
        )}

        <label
          className="grid aspect-[4/3] cursor-pointer place-items-center rounded-[10px] border-2 border-dashed border-border-strong bg-surface-raised transition-colors hover:border-primary"
          style={{
            backgroundImage:
              'repeating-linear-gradient(135deg, color-mix(in srgb,var(--primary) 7%,transparent) 0 12px, transparent 12px 24px)',
          }}
        >
          <div className="text-center text-text-muted">
            <Camera size={24} className="mx-auto mb-1.5" />
            <div className="flex items-center gap-1.5 text-[13px] font-medium">
              <Upload size={14} />
              {busy ? 'Uploading…' : 'Upload a new cover photo'}
            </div>
            <div className="mt-0.5 text-[11px] text-text-subtle">JPG or PNG, up to 12 MB</div>
          </div>
          <input
            type="file"
            accept="image/*"
            className="hidden"
            disabled={busy}
            onChange={onUpload}
          />
        </label>

        {failed && (
          <div className="text-[12px] text-overdue">Could not save the cover photo. Try again.</div>
        )}
      </div>
    </Modal>
  )
}
