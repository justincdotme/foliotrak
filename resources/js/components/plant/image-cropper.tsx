import { useState } from 'react'
import Cropper from 'react-easy-crop'
import type { Area } from 'react-easy-crop'
import { ZoomIn } from 'lucide-react'
import type { CropArea } from '@/api/types'
import { cn } from '@/lib/utils'

interface ImageCropperProps {
  image: string
  aspect: number
  onCropComplete: (area: CropArea) => void
  containerClass?: string
}

export function ImageCropper({ image, aspect, onCropComplete, containerClass }: ImageCropperProps) {
  const [crop, setCrop] = useState({ x: 0, y: 0 })
  const [zoom, setZoom] = useState(1)

  const handleCropComplete = (_: Area, pixels: Area) => {
    onCropComplete(pixels)
  }

  return (
    <div className="space-y-3">
      <div className={cn('relative overflow-hidden rounded-lg bg-black/80', containerClass)}>
        <Cropper
          image={image}
          crop={crop}
          zoom={zoom}
          aspect={aspect}
          onCropChange={setCrop}
          onZoomChange={setZoom}
          onCropComplete={handleCropComplete}
        />
      </div>
      <div className="flex items-center gap-3 text-text-muted">
        <ZoomIn size={16} className="shrink-0" />
        <input
          type="range"
          min={1}
          max={3}
          step={0.01}
          value={zoom}
          onChange={e => setZoom(parseFloat(e.target.value))}
          className="h-1.5 w-full cursor-pointer appearance-none rounded-full bg-border accent-primary"
          aria-label="Zoom"
        />
      </div>
    </div>
  )
}
