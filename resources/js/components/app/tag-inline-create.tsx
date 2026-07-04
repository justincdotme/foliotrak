import { useState, useEffect, useRef } from 'react'
import { Check, Plus, X } from 'lucide-react'
import type { Tag } from '@/api/types'
import { Chip } from '@/components/app/chip'
import { FormError } from '@/components/app/form-error'
import { InlineCombobox } from '@/components/app/inline-combobox'
import { useCreateTag } from '@/hooks/useTags'

interface TagInlineCreateProps {
  allTags: Tag[]
  selectedTags: Tag[]
  onToggle: (tag: Tag) => void
}

export function TagInlineCreate({ allTags, selectedTags, onToggle }: TagInlineCreateProps) {
  const createTag = useCreateTag()
  const [adding, setAdding] = useState(false)
  const [createError, setCreateError] = useState<string | null>(null)
  const comboRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (adding) comboRef.current?.querySelector('input')?.focus()
  }, [adding])

  const selectedIds = new Set(selectedTags.map(t => t.id))
  const availableTags = allTags.filter(t => !selectedIds.has(t.id))

  const handleSelect = (tag: Tag) => {
    onToggle(tag)
    setAdding(false)
    setCreateError(null)
  }

  const handleCreate = async (name: string) => {
    const existing = allTags.find(t => t.name.toLowerCase() === name.toLowerCase())
    if (existing) {
      handleSelect(existing)
      return
    }
    setCreateError(null)
    try {
      const created = await createTag.mutateAsync(name)
      onToggle(created)
      setAdding(false)
    } catch (err) {
      setCreateError('Could not create tag.')
      throw err
    }
  }

  return (
    <>
      {selectedTags.map(t => (
        <Chip key={t.id} color={t.color || 'var(--series-1)'} active onClick={() => onToggle(t)}>
          <Check size={12} />
          {t.name}
        </Chip>
      ))}
      {!adding && (
        <button
          type="button"
          onClick={() => setAdding(true)}
          className="inline-flex items-center gap-1 h-7 rounded-full border border-dashed border-border-strong px-2.5 text-[12px] font-medium text-text-muted hover:text-text hover:border-text transition-colors cursor-pointer"
        >
          <Plus size={12} />
          Tag
        </button>
      )}
      {adding && (
        <div className="relative w-full mt-1.5">
          <div className="flex items-center gap-1.5">
            <div ref={comboRef} className="flex-1">
              <InlineCombobox
                items={availableTags}
                getItemValue={t => t.name}
                renderItem={t => (
                  <span className="flex items-center gap-2">
                    <span
                      className="h-2.5 w-2.5 rounded-full shrink-0"
                      style={{ background: t.color || 'var(--series-1)' }}
                    />
                    {t.name}
                  </span>
                )}
                onSelect={handleSelect}
                onCreate={handleCreate}
                placeholder="Type a tag name…"
                icon={null}
                className="h-8 text-[13px]"
              />
            </div>
            <button
              type="button"
              onClick={() => {
                setAdding(false)
                setCreateError(null)
              }}
              className="shrink-0 p-1 text-text-muted hover:text-text"
            >
              <X size={14} />
            </button>
          </div>
          <FormError message={createError} />
        </div>
      )}
    </>
  )
}
