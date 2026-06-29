import { useState, useRef, useEffect, type KeyboardEvent } from 'react'
import { AlertTriangle, Check, Plus, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { inputClass } from '@/components/ui/input'
import { Chip } from '@/components/app/chip'
import { useCreateTag } from '@/hooks/useTags'
import type { Tag } from '@/api/types'

interface TagInlineCreateProps {
  allTags: Tag[]
  selectedTags: Tag[]
  onToggle: (tag: Tag) => void
}

export function TagInlineCreate({ allTags, selectedTags, onToggle }: TagInlineCreateProps) {
  const createTag = useCreateTag()
  const [adding, setAdding] = useState(false)
  const [query, setQuery] = useState('')
  const [createError, setCreateError] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)
  const containerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (adding) inputRef.current?.focus()
  }, [adding])

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setAdding(false)
        setQuery('')
      }
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const trimmed = query.trim()
  const selectedIds = new Set(selectedTags.map(t => t.id))

  const matches = trimmed
    ? allTags.filter(
        t => !selectedIds.has(t.id) && t.name.toLowerCase().includes(trimmed.toLowerCase())
      )
    : allTags.filter(t => !selectedIds.has(t.id))

  const exactMatch = allTags.some(t => t.name.toLowerCase() === trimmed.toLowerCase())
  const showCreate = trimmed.length > 0 && !exactMatch

  const selectExisting = (tag: Tag) => {
    onToggle(tag)
    setAdding(false)
    setQuery('')
    setCreateError(null)
  }

  const create = async (name: string) => {
    const existing = allTags.find(t => t.name.toLowerCase() === name.toLowerCase())
    if (existing) {
      selectExisting(existing)
      return
    }
    setCreateError(null)
    try {
      const created = await createTag.mutateAsync(name)
      onToggle(created)
      setAdding(false)
      setQuery('')
    } catch {
      setCreateError('Could not create tag.')
    }
  }

  const onKey = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Escape') {
      setAdding(false)
      setQuery('')
    } else if (e.key === 'Enter') {
      e.preventDefault()
      const first = matches[0]
      if (first && matches.length === 1 && !showCreate) {
        selectExisting(first)
      } else if (showCreate) {
        create(trimmed)
      } else if (first) {
        selectExisting(first)
      }
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
        <div ref={containerRef} className="relative w-full mt-1.5">
          <div className="flex items-center gap-1.5">
            <input
              ref={inputRef}
              value={query}
              onChange={e => setQuery(e.target.value)}
              onKeyDown={onKey}
              placeholder="Type a tag name…"
              className={cn(inputClass, 'h-8 text-[13px] flex-1')}
            />
            <button
              type="button"
              onClick={() => {
                setAdding(false)
                setQuery('')
                setCreateError(null)
              }}
              className="shrink-0 p-1 text-text-muted hover:text-text"
            >
              <X size={14} />
            </button>
          </div>
          {(matches.length > 0 || showCreate) && (
            <div className="absolute left-0 right-0 z-10 mt-1 max-h-40 overflow-y-auto rounded-[10px] border border-border bg-surface-raised shadow-xl">
              {matches.map(t => (
                <button
                  key={t.id}
                  type="button"
                  onClick={() => selectExisting(t)}
                  className="w-full flex items-center gap-2 px-3 py-1.5 text-left text-[13px] hover:bg-surface"
                >
                  <span
                    className="h-2.5 w-2.5 rounded-full shrink-0"
                    style={{ background: t.color || 'var(--series-1)' }}
                  />
                  {t.name}
                </button>
              ))}
              {showCreate && (
                <button
                  type="button"
                  onClick={() => create(trimmed)}
                  disabled={createTag.isPending}
                  className="w-full border-t border-border px-3 py-1.5 text-left text-[13px] text-text-muted hover:bg-surface flex items-center gap-2"
                >
                  <Plus size={14} />
                  Create &quot;{trimmed}&quot;
                </button>
              )}
            </div>
          )}
          {createError && (
            <div className="mt-1 flex items-center gap-1 text-[12px] text-overdue">
              <AlertTriangle size={12} />
              {createError}
            </div>
          )}
        </div>
      )}
    </>
  )
}
