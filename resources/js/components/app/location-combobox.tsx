import { useState, useRef, useEffect, type KeyboardEvent } from 'react'
import { MapPin, Plus } from 'lucide-react'
import { cn } from '@/lib/utils'
import { inputClass } from '@/components/ui/input'
import { useLocations, useCreateLocation } from '@/hooks/useLocations'
import type { Location } from '@/api/types'

interface LocationComboboxProps {
  value: number | null
  onChange: (locationId: number | null) => void
  placeholder?: string
}

export function LocationCombobox({
  value,
  onChange,
  placeholder = 'Living room shelf',
}: LocationComboboxProps) {
  const { data: locations } = useLocations()
  const createLocation = useCreateLocation()
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  const [active, setActive] = useState(-1)
  const inputRef = useRef<HTMLInputElement>(null)
  const containerRef = useRef<HTMLDivElement>(null)

  const selected = value != null ? locations.find(l => l.id === value) : null

  useEffect(() => {
    if (!open) setQuery(selected?.name ?? '')
  }, [open, selected])

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const trimmed = query.trim()
  const filtered = trimmed
    ? locations.filter(l => l.name.toLowerCase().includes(trimmed.toLowerCase()))
    : locations
  const exactMatch = locations.some(l => l.name.toLowerCase() === trimmed.toLowerCase())
  const showCreate = trimmed.length > 0 && !exactMatch

  const options: Array<
    { type: 'location'; location: Location } | { type: 'create'; name: string }
  > = [
    ...filtered.map(l => ({ type: 'location' as const, location: l })),
    ...(showCreate ? [{ type: 'create' as const, name: trimmed }] : []),
  ]

  const pick = (loc: Location) => {
    onChange(loc.id)
    setQuery(loc.name)
    setOpen(false)
    setActive(-1)
  }

  const create = async (name: string) => {
    const loc = await createLocation.mutateAsync(name)
    pick(loc)
  }

  const onKey = (e: KeyboardEvent<HTMLInputElement>) => {
    if (!open) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        setOpen(true)
        e.preventDefault()
      }
      return
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setActive(a => Math.min(a + 1, options.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setActive(a => Math.max(a - 1, 0))
    } else if (e.key === 'Enter') {
      e.preventDefault()
      const opt = options[active]
      if (opt?.type === 'location') pick(opt.location)
      else if (opt?.type === 'create') create(opt.name)
    } else if (e.key === 'Escape') {
      setOpen(false)
    }
  }

  return (
    <div ref={containerRef} className="relative">
      <div className="relative">
        <MapPin size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-text-subtle" />
        <input
          ref={inputRef}
          value={open ? query : (selected?.name ?? '')}
          onChange={e => {
            setQuery(e.target.value)
            setOpen(true)
            setActive(-1)
            if (e.target.value.trim() === '') onChange(null)
          }}
          onFocus={() => {
            setQuery(selected?.name ?? '')
            setOpen(true)
          }}
          onKeyDown={onKey}
          placeholder={placeholder}
          className={cn(inputClass, 'pl-9')}
          role="combobox"
          aria-expanded={open}
          aria-autocomplete="list"
          aria-controls="location-list"
        />
      </div>
      {open && (
        <div
          id="location-list"
          role="listbox"
          className="absolute left-0 right-0 z-10 mt-1 max-h-48 overflow-y-auto rounded-[10px] border border-border bg-surface-raised shadow-xl"
        >
          {options.length === 0 && (
            <div className="px-3 py-2 text-[12px] text-text-subtle">Type a location name</div>
          )}
          {options.map((opt, i) =>
            opt.type === 'location' ? (
              <button
                key={opt.location.id}
                type="button"
                role="option"
                aria-selected={active === i}
                onMouseEnter={() => setActive(i)}
                onClick={() => pick(opt.location)}
                className={cn(
                  'w-full px-3 py-2 text-left text-[13px]',
                  active === i ? 'bg-surface' : '',
                  opt.location.id === value ? 'text-primary font-medium' : ''
                )}
              >
                {opt.location.name}
              </button>
            ) : (
              <button
                key="__create__"
                type="button"
                role="option"
                aria-selected={active === i}
                onMouseEnter={() => setActive(i)}
                onClick={() => create(opt.name)}
                disabled={createLocation.isPending}
                className={cn(
                  'w-full border-t border-border px-3 py-2 text-left text-[13px] text-text-muted hover:bg-surface flex items-center gap-2',
                  active === i ? 'bg-surface' : ''
                )}
              >
                <Plus size={14} />
                Create &quot;{opt.name}&quot;
              </button>
            )
          )}
        </div>
      )}
    </div>
  )
}
