import { useState, useEffect, useRef } from 'react'
import { Check, Plus, X } from 'lucide-react'
import type { PlantSensor, Sensor } from '@/api/types'
import { Chip } from '@/components/app/chip'
import { InlineCombobox } from '@/components/app/inline-combobox'
import { formatSensorLabel } from '@/lib/format'

interface SensorSelectProps {
  allSensors: Sensor[]
  selectedSensors: PlantSensor[]
  onToggle: (sensor: PlantSensor) => void
}

export function SensorSelect({ allSensors, selectedSensors, onToggle }: SensorSelectProps) {
  const [adding, setAdding] = useState(false)
  const comboRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (adding) comboRef.current?.querySelector('input')?.focus()
  }, [adding])

  const selectedIds = new Set(selectedSensors.map(s => s.id))
  const availableSensors = allSensors.filter(s => !selectedIds.has(s.id))

  const handleSelect = (sensor: Sensor) => {
    onToggle(sensor)
    setAdding(false)
  }

  return (
    <>
      {selectedSensors.map(s => (
        <Chip key={s.id} color={s.color || 'var(--series-1)'} active onClick={() => onToggle(s)}>
          <Check size={12} />
          {formatSensorLabel(s.name, s.location)}
        </Chip>
      ))}
      {allSensors.length === 0 ? (
        <span className="text-[12px] text-text-subtle">Configure sensors in Settings</span>
      ) : (
        <>
          {!adding && (
            <button
              type="button"
              onClick={() => setAdding(true)}
              className="inline-flex items-center gap-1 h-7 rounded-full border border-dashed border-border-strong px-2.5 text-[12px] font-medium text-text-muted hover:text-text hover:border-text transition-colors cursor-pointer"
            >
              <Plus size={12} />
              Sensor
            </button>
          )}
          {adding && (
            <div className="relative w-full mt-1.5">
              <div className="flex items-center gap-1.5">
                <div ref={comboRef} className="flex-1">
                  <InlineCombobox
                    items={availableSensors}
                    getItemValue={s => formatSensorLabel(s.name, s.location)}
                    renderItem={s => (
                      <span className="flex items-center gap-2">
                        <span
                          className="h-2.5 w-2.5 rounded-full shrink-0"
                          style={{ background: s.color || 'var(--series-1)' }}
                        />
                        {formatSensorLabel(s.name, s.location)}
                      </span>
                    )}
                    onSelect={handleSelect}
                    placeholder="Select a sensor..."
                    icon={null}
                    className="h-8 text-[13px]"
                  />
                </div>
                <button
                  type="button"
                  onClick={() => setAdding(false)}
                  className="shrink-0 p-1 text-text-muted hover:text-text"
                >
                  <X size={14} />
                </button>
              </div>
            </div>
          )}
        </>
      )}
    </>
  )
}
