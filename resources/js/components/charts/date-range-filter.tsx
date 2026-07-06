import { Segmented } from '@/components/app/segmented'
import { DATE_RANGE_OPTIONS, type DateRange } from './chart-utils'

interface DateRangeFilterProps {
  value: DateRange
  onChange: (range: DateRange) => void
}

export function DateRangeFilter({ value, onChange }: DateRangeFilterProps) {
  return (
    <Segmented
      value={value}
      onChange={v => onChange(v as DateRange)}
      options={DATE_RANGE_OPTIONS}
    />
  )
}
