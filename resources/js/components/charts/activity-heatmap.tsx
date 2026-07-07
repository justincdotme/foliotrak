import { ResponsiveTimeRange } from '@nivo/calendar'
import { Card } from '@/components/ui/card'
import type { CareEvent } from '@/api/types'
import { calendarRange, eventsToCalendar } from './activity-calendar'

interface ActivityHeatmapProps {
  events: CareEvent[]
}

export function ActivityHeatmap({ events }: ActivityHeatmapProps) {
  const calendarData = eventsToCalendar(events)
  const { from, to } = calendarRange()

  // TimeRange renders a contiguous from->to strip, unlike the year-locked
  // Calendar, so the 60-day window stays compact.
  return (
    <Card dusk="activity-heatmap" className="p-4">
      <div className="flex items-baseline gap-2 mb-3">
        <h3 className="text-[13px] font-semibold text-text">Care activity</h3>
        <span className="text-[11px] tnum text-text-subtle ml-auto">last 60 days</span>
      </div>
      <div className="h-[160px]">
        <ResponsiveTimeRange
          data={calendarData}
          from={from}
          to={to}
          minValue={0}
          emptyColor="var(--surface-raised)"
          colors={['var(--health-4)', 'var(--primary)', 'var(--primary-hover)']}
          margin={{ top: 28, right: 8, bottom: 8, left: 8 }}
          dayRadius={2}
          daySpacing={2}
          dayBorderColor="var(--border)"
          weekdayTicks={[]}
          theme={{ text: { fontSize: 10, fill: 'var(--text-subtle)' } }}
          tooltip={({ day, value }) => (
            <div className="rounded-[6px] border border-border bg-surface-raised px-2 py-1 text-[11px] text-text shadow-sm">
              {day}: {value} event{Number(value) === 1 ? '' : 's'}
            </div>
          )}
        />
      </div>
    </Card>
  )
}
