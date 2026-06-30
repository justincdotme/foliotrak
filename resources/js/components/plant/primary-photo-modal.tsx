import { Camera, Check, Trash2, Upload } from 'lucide-react'
import { type ChangeEvent, type DragEvent, useCallback, useRef, useState } from 'react'
import type { CropArea, Photo, Plant } from '@/api/types'
import { Button } from '@/components/ui/button'
import { Modal } from '@/components/app/modal'
import { cn } from '@/lib/utils'
import { photoUrl } from '@/lib/photos'
import { useDeletePhoto, useSetCoverPhoto, useUploadPhoto } from '@/hooks/usePlantMutations'
// Extracted to prevent accidental removal (FOL-59). Guard tests in primary-photo-modal.test.tsx.
import { CropWorkflow } from './crop-workflow'

interface PrimaryPhotoModalProps {
  plant: Plant
  photos: Photo[]
  open: boolean
  onClose: () => void
}

export function PrimaryPhotoModal({ plant, photos, open, onClose }: PrimaryPhotoModalProps) {
  const upload = useUploadPhoto(plant.id)
  const setCover = useSetCoverPhoto(plant.id)
  const remove = useDeletePhoto(plant.id)
  const busy = upload.isPending || setCover.isPending || remove.isPending
  const failed = upload.isError || setCover.isError
  const inputRef = useRef<HTMLInputElement>(null)
  const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null)

  const [cropping, setCropping] = useState(false)
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<string | null>(null)
  const [dragging, setDragging] = useState(false)

  const reset = useCallback(() => {
    setCropping(false)
    setFile(null)
    if (preview) URL.revokeObjectURL(preview)
    setPreview(null)
    setDragging(false)
  }, [preview])

  const handleClose = useCallback(() => {
    reset()
    onClose()
  }, [reset, onClose])

  const loadFile = useCallback(
    (f: File) => {
      if (preview) URL.revokeObjectURL(preview)
      setFile(f)
      setPreview(URL.createObjectURL(f))
      setCropping(true)
    },
    [preview]
  )

  const onFileChange = (e: ChangeEvent<HTMLInputElement>) => {
    const f = e.target.files?.[0]
    if (f) loadFile(f)
    if (inputRef.current) inputRef.current.value = ''
  }

  const onDragOver = (e: DragEvent) => {
    e.preventDefault()
    setDragging(true)
  }
  const onDragLeave = (e: DragEvent) => {
    e.preventDefault()
    setDragging(false)
  }
  const onDrop = (e: DragEvent) => {
    e.preventDefault()
    setDragging(false)
    const f = e.dataTransfer.files[0]
    if (f?.type.startsWith('image/')) loadFile(f)
  }

  const handleCropComplete = async (heroCrop: CropArea, thumbCrop: CropArea) => {
    if (!file) return
    try {
      await upload.mutateAsync({
        file,
        caption: 'Cover photo',
        setAsCover: true,
        heroCrop,
        thumbCrop,
      })
      handleClose()
    } catch {
      // Error state shown in the crop workflow
    }
  }

  const pick = async (photoId: number) => {
    try {
      await setCover.mutateAsync(photoId)
      handleClose()
    } catch {
      // Error state shown in the modal
    }
  }

  const removePhoto = async (photoId: number) => {
    try {
      await remove.mutateAsync(photoId)
      setConfirmDeleteId(null)
    } catch {
      // Error state shown in the modal
    }
  }

  return (
    <>
      <Modal
        open={open && !cropping}
        onClose={handleClose}
        title="Cover photo"
        subtitle="The card image, separate from logged photos."
        footer={
          <Button variant="ghost" onClick={handleClose}>
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
                  const confirming = confirmDeleteId === ph.id
                  return (
                    <div key={ph.id} className="relative">
                      <button
                        type="button"
                        onClick={() => pick(ph.id)}
                        disabled={busy}
                        aria-pressed={isCover}
                        className={cn(
                          'relative aspect-square w-full overflow-hidden rounded-md border',
                          isCover ? 'border-primary ring-2 ring-primary' : 'border-border'
                        )}
                      >
                        <img
                          src={photoUrl(ph.thumb_path ?? ph.path)}
                          alt={ph.caption || 'Plant photo'}
                          className="h-full w-full object-cover"
                        />
                        {isCover && (
                          <span className="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-primary text-white">
                            <Check size={12} />
                          </span>
                        )}
                      </button>
                      {confirming ? (
                        <div className="absolute inset-0 flex flex-col items-center justify-center gap-1.5 rounded-md bg-black/70 p-2">
                          <button
                            type="button"
                            onClick={() => removePhoto(ph.id)}
                            disabled={busy}
                            className="w-full rounded-md bg-overdue px-2 py-1.5 text-[12px] font-medium text-white hover:bg-overdue/90"
                          >
                            Delete
                          </button>
                          <button
                            type="button"
                            onClick={() => setConfirmDeleteId(null)}
                            className="w-full rounded-md px-2 py-1.5 text-[12px] font-medium text-white/80 hover:bg-white/15"
                          >
                            Cancel
                          </button>
                        </div>
                      ) : (
                        <button
                          type="button"
                          onClick={() => setConfirmDeleteId(ph.id)}
                          disabled={busy}
                          className="absolute bottom-1 right-1 grid h-6 w-6 place-items-center rounded-full bg-black/50 text-white/80 hover:bg-overdue hover:text-white transition-colors"
                          aria-label="Delete photo"
                        >
                          <Trash2 size={12} />
                        </button>
                      )}
                    </div>
                  )
                })}
              </div>
            </div>
          )}

          <label
            className={cn(
              'grid aspect-[4/3] cursor-pointer place-items-center rounded-[10px] border-2 border-dashed transition-colors',
              dragging
                ? 'border-primary bg-primary/10'
                : 'border-border-strong bg-surface-raised hover:border-primary'
            )}
            style={
              dragging
                ? undefined
                : {
                    backgroundImage:
                      'repeating-linear-gradient(135deg, color-mix(in srgb,var(--primary) 7%,transparent) 0 12px, transparent 12px 24px)',
                  }
            }
            onDragOver={onDragOver}
            onDragLeave={onDragLeave}
            onDrop={onDrop}
          >
            <div className="text-center text-text-muted">
              <Camera size={24} className="mx-auto mb-1.5" />
              <div className="flex items-center gap-1.5 text-[13px] font-medium">
                <Upload size={14} />
                {dragging ? 'Drop image here' : 'Upload a new cover photo'}
              </div>
              <div className="mt-0.5 text-[11px] text-text-subtle">
                Drop an image or click to browse. JPG, PNG, or WebP.
              </div>
            </div>
            <input
              ref={inputRef}
              type="file"
              accept="image/*"
              className="hidden"
              disabled={busy}
              onChange={onFileChange}
            />
          </label>

          {failed && (
            <div className="text-[12px] text-overdue">
              Could not save the cover photo. Try again.
            </div>
          )}
        </div>
      </Modal>
      {cropping && preview && (
        <CropWorkflow
          preview={preview}
          onBack={() => setCropping(false)}
          onComplete={handleCropComplete}
          onClose={handleClose}
          busy={upload.isPending}
          failed={upload.isError}
        />
      )}
    </>
  )
}
