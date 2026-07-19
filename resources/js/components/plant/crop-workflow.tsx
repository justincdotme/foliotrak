import { useCallback, useState } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import type { CropArea } from '@/api/types'
import { Button } from '@/components/ui/button'
import { TooltipButton } from '@/components/ui/tooltip-button'
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
  const [confirmingClose, setConfirmingClose] = useState(false)

  const requestClose = () => setConfirmingClose(true)

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
      onClose={requestClose}
      title={title}
      subtitle={subtitle}
      wide
      footer={
        confirmingClose ? (
          <div className="-m-4 flex w-[calc(100%+2rem)] items-center gap-2 rounded-b-card bg-overdue/10 p-4">
            <span className="mr-auto text-[13px] font-medium text-overdue">Discard this crop?</span>
            <Button variant="ghost" size="sm" onClick={() => setConfirmingClose(false)}>
              Keep editing
            </Button>
            <Button variant="danger" size="sm" onClick={onClose}>
              Discard
            </Button>
          </div>
        ) : step === 'hero' ? (
          <div className="flex w-full items-center justify-between">
            <Button variant="ghost" onClick={handleBack}>
              <ChevronLeft size={16} />
              Back
            </Button>
            <TooltipButton
              onClick={() => setStep('thumb')}
              disabled={!heroCropArea}
              tooltipContent={!heroCropArea ? 'Crop the cover photo first' : undefined}
            >
              Next
              <ChevronRight size={16} />
            </TooltipButton>
          </div>
        ) : (
          <div className="flex w-full items-center justify-between">
            <Button variant="ghost" onClick={handleBack}>
              <ChevronLeft size={16} />
              Back
            </Button>
            <TooltipButton
              onClick={handleSubmit}
              disabled={!thumbCropArea || busy}
              tooltipContent={
                busy ? 'Uploading...' : !thumbCropArea ? 'Crop the thumbnail first' : undefined
              }
            >
              {busy ? 'Uploading...' : 'Save cover photo'}
            </TooltipButton>
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
