import { useState } from 'react'
import { MapPin } from 'lucide-react'
import type { Location } from '@/api/types'
import { useLocations, useCreateLocation } from '@/hooks/useLocations'
import { FormError } from '@/components/app/form-error'
import { InlineCombobox } from '@/components/app/inline-combobox'

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
  const [createError, setCreateError] = useState<string | null>(null)

  const selected = value != null ? locations.find(l => l.id === value) : null

  const handleQueryChange = (q: string) => {
    setQuery(q)
    if (q.trim() === '') onChange(null)
  }

  const handleOpenChange = (o: boolean) => {
    if (o) {
      setQuery(selected?.name ?? '')
    } else {
      setCreateError(null)
    }
    setOpen(o)
  }

  const handleSelect = (loc: Location) => {
    onChange(loc.id)
    setQuery(loc.name)
  }

  const handleCreate = async (name: string) => {
    setCreateError(null)
    try {
      const loc = await createLocation.mutateAsync(name)
      onChange(loc.id)
      setQuery(loc.name)
    } catch (err) {
      setCreateError('Could not create location.')
      throw err
    }
  }

  return (
    <>
      <InlineCombobox
        items={locations}
        getItemValue={l => l.name}
        query={open ? query : (selected?.name ?? '')}
        onQueryChange={handleQueryChange}
        open={open}
        onOpenChange={handleOpenChange}
        onSelect={handleSelect}
        onCreate={handleCreate}
        placeholder={placeholder}
        icon={<MapPin size={16} />}
      />
      <FormError message={createError} />
    </>
  )
}
