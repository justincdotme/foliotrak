import { Bell, Check, Droplets, FlaskConical } from 'lucide-react'
import { useEffect, useState } from 'react'
import type { Plant } from '@/api/types'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/app/field'
import { Input } from '@/components/ui/input'
import { WaterDrop } from '@/components/app/water-drop'
import { fmtDateY, relDay } from '@/lib/format'
import { useNotification } from '@/components/app/notification-context'
import { handleApiError } from '@/lib/handle-api-error'
import { useUpdatePlant } from '@/hooks/usePlantMutations'

export type NextDue = {
  due_date: string
  daysLeft: number
  status: 'ok' | 'due-soon' | 'overdue'
  type: 'watering'
  interval: number
  last_watered: string
} | null

const today = (): string => new Date().toISOString().slice(0, 10)

function NextDueRow({ due, lastWateredAt }: { due: NextDue; lastWateredAt?: string | null }) {
  if (!due) {
    if (lastWateredAt) {
      return (
        <div className="text-[13px] text-text-muted">
          Last watered {relDay(lastWateredAt).toLowerCase()}.
        </div>
      )
    }
    return <div className="text-[13px] text-text-muted">No watering logged yet.</div>
  }

  if (due.status === 'overdue')
    return (
      <div
        className="rounded-[8px] p-3 flex items-center gap-3"
        style={{
          background: 'color-mix(in srgb,var(--overdue) 12%,transparent)',
          border: '1px solid color-mix(in srgb,var(--overdue) 35%,transparent)',
        }}
      >
        <WaterDrop due={due} size={30} />
        <div>
          <div className="font-semibold" style={{ color: 'var(--overdue)' }}>
            Overdue by {Math.abs(due.daysLeft)} day{Math.abs(due.daysLeft) === 1 ? '' : 's'}
          </div>
          <div className="text-[12px] text-text-muted">
            Was due {fmtDateY(due.due_date)}. Water now.
          </div>
        </div>
      </div>
    )

  if (due.status === 'due-soon')
    return (
      <div
        className="rounded-[8px] p-3 flex items-center gap-3"
        style={{
          background: 'color-mix(in srgb,var(--due-soon) 12%,transparent)',
          border: '1px solid color-mix(in srgb,var(--due-soon) 35%,transparent)',
        }}
      >
        <WaterDrop due={due} size={30} />
        <div>
          <div className="font-semibold" style={{ color: 'var(--due-soon)' }}>
            {due.daysLeft <= 0 ? 'Due today' : 'Due tomorrow'}
          </div>
          <div className="text-[12px] text-text-muted">Next watering {fmtDateY(due.due_date)}.</div>
        </div>
      </div>
    )

  return (
    <div className="rounded-[8px] p-3 flex items-center gap-3 border border-border">
      <WaterDrop due={due} size={30} />
      <div>
        <div className="font-medium flex items-center gap-1.5">
          <Check size={15} className="text-ok" />
          On track
        </div>
        <div className="text-[12px] text-text-muted">
          Next watering in {due.daysLeft} days · {fmtDateY(due.due_date)}.
        </div>
      </div>
    </div>
  )
}

interface MyScheduleTabProps {
  plant: Plant
  due: NextDue
}

export function MyScheduleTab({ plant, due }: MyScheduleTabProps) {
  const { showError } = useNotification()
  const [editing, setEditing] = useState(false)
  const [wInt, setWInt] = useState(plant.watering_interval_days_override ?? '')
  const [fInt, setFInt] = useState(plant.fertilizing_interval_days_override ?? '')
  const [wStart, setWStart] = useState(plant.watering_schedule_start_date ?? today())

  useEffect(() => {
    setWInt(plant.watering_interval_days_override ?? '')
    setFInt(plant.fertilizing_interval_days_override ?? '')
    setWStart(plant.watering_schedule_start_date ?? today())
  }, [
    plant.id,
    plant.watering_interval_days_override,
    plant.fertilizing_interval_days_override,
    plant.watering_schedule_start_date,
  ])

  const hasMine =
    plant.watering_interval_days_override != null ||
    plant.fertilizing_interval_days_override != null

  const update = useUpdatePlant(plant.id)

  const save = async () => {
    try {
      await update.mutateAsync({
        watering_interval_days_override: wInt !== '' ? Number(wInt) : null,
        watering_schedule_start_date: wStart || null,
        fertilizing_interval_days_override: fInt !== '' ? Number(fInt) : null,
      })
      setEditing(false)
    } catch (err) {
      showError(handleApiError(err))
    }
  }

  return (
    <>
      <NextDueRow due={due} lastWateredAt={plant.last_watered_at} />
      {!editing ? (
        <div className="space-y-2">
          <div className="flex items-center gap-2.5 text-[13px]">
            <Droplets size={16} className="text-info shrink-0" />
            <span className="text-text-muted">Watering</span>
            <span className="ml-auto font-medium">
              {plant.watering_interval_days_override != null ? (
                <>
                  every <span className="tnum">{plant.watering_interval_days_override}</span> days
                </>
              ) : (
                <span className="text-text-subtle">not set</span>
              )}
            </span>
          </div>
          {plant.watering_schedule_start_date && (
            <div
              dusk="schedule-start-display"
              className="flex items-center gap-2.5 text-[13px] pl-[26px]"
            >
              <span className="text-text-muted">starting</span>
              <span className="ml-auto font-medium">
                {fmtDateY(plant.watering_schedule_start_date)}
              </span>
            </div>
          )}
          <div className="flex items-center gap-2.5 text-[13px]">
            <FlaskConical size={16} className="text-accent shrink-0" />
            <span className="text-text-muted">Fertilizing</span>
            <span className="ml-auto font-medium">
              {plant.fertilizing_interval_days_override != null ? (
                <>
                  every <span className="tnum">{plant.fertilizing_interval_days_override}</span>{' '}
                  days
                </>
              ) : (
                <span className="text-text-subtle">not set</span>
              )}
            </span>
          </div>
          <Button
            size="sm"
            variant="outline"
            className="w-full mt-1"
            onClick={() => setEditing(true)}
          >
            <Check size={14} />
            {hasMine ? 'Edit schedule' : 'Set a schedule'}
          </Button>
        </div>
      ) : (
        <div className="space-y-3 rounded-[8px] border border-border bg-surface-raised p-3">
          <Field label="Water every" hint="days">
            <Input
              type="number"
              min="1"
              value={wInt}
              onChange={e => setWInt(e.target.value)}
              placeholder="e.g. 5"
            />
          </Field>
          <Field label="Starting" hint="schedule anchor date">
            <Input
              dusk="schedule-start-date"
              type="date"
              value={wStart}
              onChange={e => setWStart(e.target.value)}
            />
          </Field>
          <Field label="Fertilize every" hint="days, optional">
            <Input
              type="number"
              min="1"
              value={fInt}
              onChange={e => setFInt(e.target.value)}
              placeholder="e.g. 28"
            />
          </Field>
          <div className="flex gap-2 justify-end">
            <Button size="sm" variant="ghost" onClick={() => setEditing(false)}>
              Cancel
            </Button>
            <Button size="sm" onClick={save} disabled={update.isPending}>
              <Check size={14} />
              Save schedule
            </Button>
          </div>
        </div>
      )}
      <p className="text-[11px] text-text-subtle flex items-center gap-1.5">
        <Bell size={12} />
        Reminders fire on this cadence. Leave unset to follow the recommended schedule.
      </p>
    </>
  )
}
