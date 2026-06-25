import { Bell, Check, Clock, Info, Droplets, FlaskConical, Calendar } from 'lucide-react'
import { useEffect, useState } from 'react'
import type { CareEvent, Plant, Recommendation } from '@/api/types'
import { mockApi } from '@/api/mock'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Field } from '@/components/app/field'
import { Input } from '@/components/ui/input'
import { SectionTitle } from '@/components/app/section-title'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { WaterDrop } from '@/components/app/water-drop'
import { ageDays, fmtDateY } from '@/lib/format'
import { commit } from '@/hooks/useAsync'

type NextDue = {
  due_date: string
  daysLeft: number
  status: 'ok' | 'due-soon' | 'overdue'
  type: 'watering'
  interval: number
  last_watered: string
} | null

interface ScheduleSectionProps {
  plant: Plant
  recs: Recommendation[]
  due: NextDue
  events: CareEvent[]
}

function NextDueRow({ due }: { due: NextDue }) {
  if (!due) return <div className="text-[13px] text-text-muted">No watering logged yet.</div>

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

export function ScheduleSection({ plant, recs, due, events }: ScheduleSectionProps) {
  const [tab, setTab] = useState('mine')
  const [editing, setEditing] = useState(false)
  const [wInt, setWInt] = useState(plant.watering_interval_days_override ?? '')
  const [fInt, setFInt] = useState(plant.fertilizing_interval_days_override ?? '')

  useEffect(() => {
    setWInt(plant.watering_interval_days_override ?? '')
    setFInt(plant.fertilizing_interval_days_override ?? '')
  }, [plant.id, plant.watering_interval_days_override, plant.fertilizing_interval_days_override])

  const firstEvent = events
    .filter(e => e.plant_id === plant.id)
    .sort((a, b) => new Date(a.occurred_at).getTime() - new Date(b.occurred_at).getTime())[0]

  const historyDays = firstEvent ? ageDays(firstEvent.occurred_at) : 0
  const unlocked = historyDays >= 28
  const weeks = Math.round(historyDays / 7)
  const recWater = recs.find(r => r.type === 'watering')
  const recFert = recs.find(r => r.type === 'fertilizing')
  const hasMine =
    plant.watering_interval_days_override != null ||
    plant.fertilizing_interval_days_override != null

  const save = async () => {
    await mockApi.updatePlant(plant.id, {
      watering_interval_days_override: wInt !== '' ? Number(wInt) : null,
      fertilizing_interval_days_override: fInt !== '' ? Number(fInt) : null,
    })
    commit()
    setEditing(false)
  }

  const applyRec = async (which: 'watering' | 'fertilizing', days: number) => {
    await mockApi.updatePlant(
      plant.id,
      which === 'watering'
        ? { watering_interval_days_override: days }
        : { fertilizing_interval_days_override: days }
    )
    commit()
    setTab('mine')
  }

  return (
    <Card className="p-4">
      <SectionTitle icon={Calendar}>Schedule</SectionTitle>
      <Tabs value={tab} onValueChange={setTab} className="w-full">
        <TabsList className="grid w-full grid-cols-2 mb-3">
          <TabsTrigger value="mine">My schedule</TabsTrigger>
          <TabsTrigger value="rec">
            Recommended
            {!unlocked && (
              <span className="ml-1 text-[10px] px-1.5 py-0.5 rounded-full bg-border-strong text-text-subtle">
                soon
              </span>
            )}
          </TabsTrigger>
        </TabsList>

        <TabsContent value="mine" className="space-y-3">
          <NextDueRow due={due} />
          {!editing ? (
            <div className="space-y-2">
              <div className="flex items-center gap-2.5 text-[13px]">
                <Droplets size={16} className="text-info shrink-0" />
                <span className="text-text-muted">Watering</span>
                <span className="ml-auto font-medium">
                  {plant.watering_interval_days_override != null ? (
                    <>
                      every <span className="tnum">{plant.watering_interval_days_override}</span>{' '}
                      days
                    </>
                  ) : (
                    <span className="text-text-subtle">not set</span>
                  )}
                </span>
              </div>
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
                <Button size="sm" onClick={save}>
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
        </TabsContent>

        <TabsContent value="rec" className="space-y-3">
          {!unlocked ? (
            <div className="rounded-[8px] border border-dashed border-border-strong bg-surface-raised p-4 text-center opacity-95">
              <Clock size={22} className="mx-auto text-text-subtle mb-2" />
              <div className="font-medium">Keep logging.</div>
              <div className="text-[13px] text-text-muted mt-1">
                {28 - historyDays} day{28 - historyDays === 1 ? '' : 's'} remaining to unlock
                recommendations.
              </div>
              <div className="mt-3 h-1.5 rounded-full bg-border overflow-hidden">
                <div
                  className="h-full bg-primary"
                  style={{ width: Math.min(100, (historyDays / 28) * 100) + '%' }}
                />
              </div>
              <div className="text-[11px] text-text-subtle mt-1.5 tnum">
                {historyDays} of 28 days of history
              </div>
              <div className="mt-3 grid grid-cols-2 gap-2 text-left opacity-50 pointer-events-none">
                <div className="rounded-[8px] border border-border p-2.5">
                  <div className="text-[11px] text-text-subtle">Watering</div>
                  <div className="font-medium text-text-subtle">Locked</div>
                </div>
                <div className="rounded-[8px] border border-border p-2.5">
                  <div className="text-[11px] text-text-subtle">Fertilizing</div>
                  <div className="font-medium text-text-subtle">Locked</div>
                </div>
              </div>
            </div>
          ) : (
            <>
              {recWater && recWater.interval_days != null && (
                <div className="rounded-[8px] border border-border p-3">
                  <div className="flex items-start gap-3">
                    <Droplets size={18} className="text-info mt-0.5 shrink-0" />
                    <div className="flex-1">
                      <div className="font-medium">
                        Water about every <span className="tnum">{recWater.interval_days}</span>{' '}
                        days
                        {recWater.amount_ml && (
                          <>
                            , about <span className="tnum">{recWater.amount_ml} ml</span>
                          </>
                        )}
                      </div>
                      <div className="text-[12px] text-text-subtle mt-0.5">
                        Based on {recWater.sample_size} waterings over {weeks} weeks.
                      </div>
                    </div>
                  </div>
                  {plant.watering_interval_days_override !== recWater.interval_days && (
                    <Button
                      size="sm"
                      variant="ghost"
                      className="mt-2"
                      onClick={() => applyRec('watering', recWater.interval_days ?? 0)}
                    >
                      <Check size={14} />
                      Use this for my schedule
                    </Button>
                  )}
                </div>
              )}
              {recFert && recFert.interval_days != null && (
                <div className="rounded-[8px] border border-border p-3">
                  <div className="flex items-start gap-3">
                    <FlaskConical size={18} className="text-accent mt-0.5 shrink-0" />
                    <div className="flex-1">
                      <div className="font-medium">
                        Fertilize about every <span className="tnum">{recFert.interval_days}</span>{' '}
                        days
                      </div>
                      <div className="text-[12px] text-text-subtle mt-0.5">
                        Based on {recFert.sample_size} feedings. Small sample; a starting point.
                      </div>
                    </div>
                  </div>
                  {plant.fertilizing_interval_days_override !== recFert.interval_days && (
                    <Button
                      size="sm"
                      variant="ghost"
                      className="mt-2"
                      onClick={() => applyRec('fertilizing', recFert.interval_days ?? 0)}
                    >
                      <Check size={14} />
                      Use this for my schedule
                    </Button>
                  )}
                </div>
              )}
              <p className="text-[11px] text-text-subtle flex items-center gap-1.5">
                <Info size={12} />
                Suggestions reflect your own logged cadence, not a universal rule.
              </p>
            </>
          )}
        </TabsContent>
      </Tabs>
    </Card>
  )
}
