import { useCallback, useState } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import type { CropArea } from '@/api/types'
import { Button } from '@/components/ui/button'
import { Modal } from '@/components/app/modal'
import { ImageCropper } from './image-cropper'

const HERO_ASPECT = 2 / 3
const THUMB_ASPECT = 1

interface CropWorkflowProps {
  preview: string
  onBack: () => void
  onComplete: (heroCrop: CropArea, thumbCrop: CropArea) => void
  onClose: () => void
  busy: boolean
  failed: boolean
}

export function CropWorkflow({
  preview,
  onBack,
  onComplete,
  onClose,
  busy,
  failed,
}: CropWorkflowProps) {
  const [step, setStep] = useState<'hero' | 'thumb'>('hero')
  const [heroCropArea, setHeroCropArea] = useState<CropArea | null>(null)
  const [thumbCropArea, setThumbCropArea] = useState<CropArea | null>(null)

  const handleBack = useCallback(() => {
    if (step === 'thumb') {
      setStep('hero')
    } else {
      onBack()
    }
  }, [step, onBack])

  const handleSubmit = useCallback(() => {
    if (heroCropArea && thumbCropArea) {
      onComplete(heroCropArea, thumbCropArea)
    }
  }, [heroCropArea, thumbCropArea, onComplete])

  const title = step === 'hero' ? 'Crop hero photo (2:3)' : 'Crop thumbnail (1:1)'
  const subtitle =
    step === 'hero'
      ? 'Drag to position, scroll or use the slider to zoom.'
      : 'This square crop is used on cards.'

  return (
    <Modal
      open
      onClose={onClose}
      title={title}
      subtitle={subtitle}
      wide
      footer={
        step === 'hero' ? (
          <div className="flex w-full items-center justify-between">
            <Button variant="ghost" onClick={handleBack}>
              <ChevronLeft size={16} />
              Back
            </Button>
            <Button onClick={() => setStep('thumb')} disabled={!heroCropArea}>
              Next
              <ChevronRight size={16} />
            </Button>
          </div>
        ) : (
          <div className="flex w-full items-center justify-between">
            <Button variant="ghost" onClick={handleBack}>
              <ChevronLeft size={16} />
              Back
            </Button>
            <Button onClick={handleSubmit} disabled={!thumbCropArea || busy}>
              {busy ? 'Uploading...' : 'Save cover photo'}
            </Button>
          </div>
        )
      }
    >
      {step === 'hero' && (
        <ImageCropper
          image={preview}
          aspect={HERO_ASPECT}
          onCropComplete={setHeroCropArea}
          containerClass="aspect-[4/3]"
        />
      )}
      {step === 'thumb' && (
        <>
          <ImageCropper
            image={preview}
            aspect={THUMB_ASPECT}
            onCropComplete={setThumbCropArea}
            containerClass="aspect-square"
          />
          {failed && (
            <div className="text-[12px] text-overdue">
              Could not save the cover photo. Try again.
            </div>
          )}
        </>
      )}
    </Modal>
  )
}
