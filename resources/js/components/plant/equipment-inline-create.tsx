import { useState, useRef, useEffect, type KeyboardEvent } from 'react'
import { AlertTriangle, Plus, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { inputClass } from '@/components/ui/input'
import { useCreateEquipment } from '@/hooks/useEquipment'
import type { EquipmentOption } from '@/api/types'

interface EquipmentInlineCreateProps {
  onCreated: (equipment: EquipmentOption) => void
}

export function EquipmentInlineCreate({ onCreated }: EquipmentInlineCreateProps) {
  const createEquipment = useCreateEquipment()
  const [adding, setAdding] = useState(false)
  const [label, setLabel] = useState('')
  const [error, setError] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)
  const containerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (adding) inputRef.current?.focus()
  }, [adding])

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setAdding(false)
        setLabel('')
      }
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const create = async () => {
    const trimmed = label.trim()
    if (!trimmed) return
    setError(null)
    try {
      const created = await createEquipment.mutateAsync(trimmed)
      onCreated(created)
      setAdding(false)
      setLabel('')
    } catch {
      setError('Could not add equipment.')
    }
  }

  const onKey = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Escape') {
      setAdding(false)
      setLabel('')
    } else if (e.key === 'Enter') {
      e.preventDefault()
      create()
    }
  }

  if (!adding) {
    return (
      <button
        type="button"
        onClick={() => setAdding(true)}
        className="inline-flex items-center gap-1 h-7 rounded-full border border-dashed border-border-strong px-2.5 text-[12px] font-medium text-text-muted hover:text-text hover:border-text transition-colors cursor-pointer"
      >
        <Plus size={12} />
        New
      </button>
    )
  }

  return (
    <div ref={containerRef} className="relative w-full mt-1.5">
      <div className="flex items-center gap-1.5">
        <input
          ref={inputRef}
          value={label}
          onChange={e => setLabel(e.target.value)}
          onKeyDown={onKey}
          placeholder="New equipment name…"
          className={cn(inputClass, 'h-8 text-[13px] flex-1')}
        />
        <button
          type="button"
          onClick={() => {
            setAdding(false)
            setLabel('')
            setError(null)
          }}
          className="shrink-0 p-1 text-text-muted hover:text-text"
        >
          <X size={14} />
        </button>
      </div>
      {error && (
        <div className="mt-1 flex items-center gap-1 text-[12px] text-overdue">
          <AlertTriangle size={12} />
          {error}
        </div>
      )}
    </div>
  )
}
