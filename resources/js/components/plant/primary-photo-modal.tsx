import { Camera, Upload } from 'lucide-react'
import { useState } from 'react'
import type { Plant } from '@/api/types'
import { mockApi } from '@/api/mock'
import { Button } from '@/components/ui/button'
import { Modal } from '@/components/app/modal'
import { commit } from '@/hooks/useAsync'

interface PrimaryPhotoModalProps {
  plant: Plant
  open: boolean
  onClose: () => void
}

export function PrimaryPhotoModal({ plant, open, onClose }: PrimaryPhotoModalProps) {
  const [busy, setBusy] = useState(false)

  const save = async () => {
    setBusy(true)
    await mockApi.uploadPhoto(plant.id, undefined, 'Cover photo')
    commit()
    setBusy(false)
    onClose()
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Cover photo"
      subtitle="The card image, separate from logged photos."
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button onClick={save} disabled={busy}>
            <Upload size={16} />
            Set cover photo
          </Button>
        </>
      }
    >
      <label
        className="block aspect-[4/3] rounded-[10px] border-2 border-dashed border-border-strong bg-surface-raised grid place-items-center cursor-pointer hover:border-primary transition-colors"
        style={{
          backgroundImage:
            'repeating-linear-gradient(135deg, color-mix(in srgb,var(--primary) 7%,transparent) 0 12px, transparent 12px 24px)',
        }}
      >
        <div className="text-center text-text-muted">
          <Camera size={24} className="mx-auto mb-1.5" />
          <div className="text-[13px] font-medium">Tap to upload a cover photo</div>
          <div className="text-[11px] text-text-subtle mt-0.5">or drag an image here</div>
        </div>
        <input type="file" accept="image/*" className="hidden" />
      </label>
    </Modal>
  )
}
