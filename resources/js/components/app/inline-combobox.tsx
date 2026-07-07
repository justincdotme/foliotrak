import { Command as CommandPrimitive } from 'cmdk'
import { useState, useRef, type ReactNode } from 'react'
import { Plus, Search } from 'lucide-react'
import { cn } from '@/lib/utils'
import { inputClass } from '@/components/ui/input'
import {
  Command,
  CommandList,
  CommandEmpty,
  CommandGroup,
  CommandItem,
  CommandSeparator,
} from '@/components/ui/command'
import { Popover, PopoverAnchor, PopoverContent } from '@/components/ui/popover'

interface InlineComboboxProps<T> {
  items: T[]
  onSelect: (item: T) => void
  getItemValue: (item: T) => string
  renderItem?: (item: T) => ReactNode
  onCreate?: (name: string) => Promise<void>
  placeholder?: string
  shouldFilter?: boolean
  query?: string
  onQueryChange?: (q: string) => void
  open?: boolean
  onOpenChange?: (open: boolean) => void
  loading?: boolean
  emptyMessage?: string
  footer?: ReactNode
  className?: string
  icon?: ReactNode
  dusk?: string
  contentDusk?: string
}

export function InlineCombobox<T>({
  items,
  onSelect,
  getItemValue,
  renderItem,
  onCreate,
  placeholder,
  shouldFilter = true,
  query: controlledQuery,
  onQueryChange,
  open: controlledOpen,
  onOpenChange,
  loading = false,
  emptyMessage = 'No results',
  footer,
  className,
  icon = <Search size={16} />,
  dusk,
  contentDusk,
}: InlineComboboxProps<T>) {
  const [internalQuery, setInternalQuery] = useState('')
  const [internalOpen, setInternalOpen] = useState(false)
  const wrapperRef = useRef<HTMLDivElement>(null)

  const query = controlledQuery !== undefined ? controlledQuery : internalQuery
  const isOpen = controlledOpen !== undefined ? controlledOpen : internalOpen

  const setQuery = (q: string) => {
    if (onQueryChange) onQueryChange(q)
    else setInternalQuery(q)
  }

  const setOpen = (o: boolean) => {
    if (onOpenChange) onOpenChange(o)
    else setInternalOpen(o)
  }

  const trimmed = query.trim()
  const exactMatch = items.some(item => getItemValue(item).toLowerCase() === trimmed.toLowerCase())
  const showCreate = !!onCreate && trimmed.length > 0 && !exactMatch

  const handleCreate = async () => {
    if (!onCreate || !trimmed) return
    try {
      await onCreate(trimmed)
      setOpen(false)
    } catch {
      // Caller handles the error; popover stays open for retry
    }
  }

  return (
    <div ref={wrapperRef} className="relative">
      <Command shouldFilter={shouldFilter} className="overflow-visible bg-transparent">
        <Popover open={isOpen} onOpenChange={setOpen}>
          <PopoverAnchor asChild>
            <div className="relative">
              {icon && (
                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-text-subtle pointer-events-none">
                  {icon}
                </span>
              )}
              <CommandPrimitive.Input
                value={query}
                onValueChange={q => {
                  setQuery(q)
                  if (!isOpen) setOpen(true)
                }}
                onFocus={() => setOpen(true)}
                placeholder={placeholder}
                className={cn(inputClass, icon ? 'pl-9' : '', className)}
                dusk={dusk}
              />
            </div>
          </PopoverAnchor>
          <PopoverContent
            className="p-0 overflow-hidden"
            dusk={contentDusk}
            style={{ width: 'var(--radix-popper-anchor-width)' }}
            onOpenAutoFocus={e => e.preventDefault()}
            onInteractOutside={e => {
              if (wrapperRef.current?.contains(e.target as Node)) {
                e.preventDefault()
              }
            }}
          >
            <CommandList>
              <CommandEmpty>{loading ? 'Searching…' : emptyMessage}</CommandEmpty>
              <CommandGroup>
                {items.map(item => (
                  <CommandItem
                    key={getItemValue(item)}
                    value={getItemValue(item)}
                    onSelect={() => {
                      onSelect(item)
                      setOpen(false)
                    }}
                  >
                    {renderItem ? renderItem(item) : getItemValue(item)}
                  </CommandItem>
                ))}
              </CommandGroup>
              {showCreate && (
                <>
                  <CommandSeparator />
                  <CommandGroup>
                    <CommandItem value={`__create__${trimmed}`} onSelect={() => handleCreate()}>
                      <Plus size={14} className="mr-2" />
                      Create &quot;{trimmed}&quot;
                    </CommandItem>
                  </CommandGroup>
                </>
              )}
            </CommandList>
            {footer}
          </PopoverContent>
        </Popover>
      </Command>
    </div>
  )
}
