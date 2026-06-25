import { cn } from '@/lib/utils'

interface SegmentedOption {
  value: string
  label: string
  color?: string
}

interface SegmentedProps {
  value: string
  onChange: (v: string) => void
  options: SegmentedOption[]
}

export function Segmented({ value, onChange, options }: SegmentedProps) {
  return (
    <div
      className="inline-flex w-full gap-0 rounded-md border border-border bg-surface-raised p-1"
      role="radiogroup"
    >
      {options.map(o => (
        <button
          key={o.value}
          type="button"
          role="radio"
          aria-checked={value === o.value}
          onClick={() => onChange(o.value)}
          style={value === o.value && o.color ? { background: o.color, color: '#fff' } : undefined}
          className={cn(
            'flex-1 min-h-[36px] h-9 rounded-sm px-2 text-[13px] font-medium transition-colors',
            value === o.value
              ? o.color
                ? ''
                : 'bg-primary text-white'
              : 'text-text-muted hover:text-text'
          )}
        >
          {o.label}
        </button>
      ))}
    </div>
  )
}
