import { useState } from 'react'
import { ImageIcon } from 'lucide-react'
import { Field } from '@/components/app/field'

interface PhotoAttachProps {
  onChange: (file: File | null) => void
}

export function PhotoAttach({ onChange }: PhotoAttachProps) {
  const [photoFile, setPhotoFile] = useState<File | null>(null)

  const handleChange = (file: File | null) => {
    setPhotoFile(file)
    onChange(file)
  }

  return (
    <Field label="Photo" hint="optional">
      <label className="flex h-11 cursor-pointer items-center gap-2 rounded-[8px] border border-dashed border-border-strong bg-surface-raised px-3 text-text-muted hover:text-text">
        <ImageIcon size={16} />
        <span className="text-[13px]">{photoFile ? photoFile.name : 'Attach a photo'}</span>
        <input
          type="file"
          accept="image/*"
          className="hidden"
          onChange={e => handleChange(e.target.files?.[0] ?? null)}
        />
      </label>
    </Field>
  )
}
