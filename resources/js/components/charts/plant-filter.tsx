import { useState } from 'react'
import { Check, ChevronDown } from 'lucide-react'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

interface PlantFilterOption {
  id: number
  name: string
  color: string
}

interface PlantFilterProps {
  plants: PlantFilterOption[]
  selected: Set<number>
  onChange: (selected: Set<number>) => void
}

export function PlantFilter({ plants, selected, onChange }: PlantFilterProps) {
  const [open, setOpen] = useState(false)

  const toggle = (id: number) => {
    const next = new Set(selected)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    onChange(next)
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <button
          type="button"
          dusk="group-plant-filter"
          className="inline-flex items-center gap-1.5 h-8 shrink-0 rounded-md border border-border bg-surface-raised px-2.5 text-[12px] font-medium text-text-muted hover:text-text transition-colors"
        >
          {selected.size} of {plants.length} plants
          <ChevronDown size={13} />
        </button>
      </PopoverTrigger>
      <PopoverContent className="w-64 p-2" align="start">
        <div className="flex items-center justify-between px-1 pb-1.5 mb-1 border-b border-border">
          <span className="text-[11px] font-medium text-text-muted">Filter by plants</span>
          <div className="flex gap-2 text-[11px]">
            <button
              type="button"
              dusk="group-plant-filter-select-all"
              onClick={() => onChange(new Set(plants.map(p => p.id)))}
              className="text-primary hover:underline"
            >
              Select all
            </button>
            <button
              type="button"
              dusk="group-plant-filter-clear"
              onClick={() => onChange(new Set())}
              className="text-primary hover:underline"
            >
              Clear
            </button>
          </div>
        </div>
        <div className="max-h-64 overflow-y-auto flex flex-col gap-0.5">
          {plants.map(p => (
            <button
              key={p.id}
              type="button"
              dusk={`group-plant-filter-option-${p.id}`}
              onClick={() => toggle(p.id)}
              className="flex items-center gap-2 rounded-[6px] px-2 py-1.5 text-[13px] text-left hover:bg-surface transition-colors"
            >
              <span
                className="flex h-4 w-4 shrink-0 items-center justify-center rounded-[4px] border"
                style={{
                  backgroundColor: selected.has(p.id) ? p.color : 'transparent',
                  borderColor: p.color,
                }}
              >
                {selected.has(p.id) && <Check size={11} color="#fff" />}
              </span>
              {p.name}
            </button>
          ))}
        </div>
      </PopoverContent>
    </Popover>
  )
}
