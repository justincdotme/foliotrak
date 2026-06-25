import { AlertTriangle, Bell, Clock } from 'lucide-react'
import { Card } from '@/components/ui/card'
import { EmptyState } from '@/components/app/empty-state'
import { SectionTitle } from '@/components/app/section-title'
import { Spinner } from '@/components/app/spinner'
import { TypeIcon } from '@/components/app/type-icon'
import { WaterDrop } from '@/components/app/water-drop'
import { CARE_META } from '@/lib/domain'
import { relDay } from '@/lib/format'
import { useDashboard } from '@/hooks/useDashboard'
import type { DueForCare, CareType } from '@/api/types'

interface DashboardPageProps {
  go: (to: string) => void
}

function DueRow({ item, onClick }: { item: DueForCare; onClick: () => void }) {
  const wl = waterLabel(item)
  return (
    <button
      onClick={onClick}
      className="w-full flex items-center gap-3 p-3 rounded-[8px] hover:bg-surface-raised text-left transition-colors"
    >
      <div className="bg-surface-raised rounded-full p-0.5 shrink-0">
        <WaterDrop due={item} size={28} />
      </div>
      <div className="min-w-0 flex-1">
        <div className="font-medium truncate">{item.common_name}</div>
        <div className="text-[12px] text-text-subtle italic truncate">{item.scientific_name}</div>
      </div>
      <div
        className="text-right shrink-0 text-[12px] font-medium flex items-center gap-1.5"
        style={{ color: wl.color }}
      >
        {item.status === 'overdue' && <AlertTriangle size={13} />}
        {item.daysLeft < 0
          ? `${Math.abs(item.daysLeft)}d overdue`
          : item.daysLeft === 0
            ? 'today'
            : `in ${item.daysLeft}d`}
      </div>
    </button>
  )
}

function waterLabel(due: DueForCare) {
  if (due.status === 'overdue')
    return { text: `Water ${Math.abs(due.daysLeft)}d overdue`, color: 'var(--overdue)' }
  if (due.status === 'due-soon')
    return {
      text: due.daysLeft <= 0 ? 'Water today' : 'Water due soon',
      color: 'var(--due-soon)',
    }
  return { text: `Water in ${due.daysLeft}d`, color: 'var(--text-muted)' }
}

export function DashboardPage({ go }: DashboardPageProps) {
  const { data, loading } = useDashboard()

  if (loading) return <Spinner />

  if (!data) {
    return <EmptyState title="Dashboard">Unable to load dashboard data.</EmptyState>
  }

  const d = data
  const attentionCount = d.due_for_care.filter(x => x.status !== 'ok').length

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">Good morning, {d.user.name.split(' ')[0]}</h1>
        <p className="text-text-muted text-[13px] mt-0.5">
          {attentionCount} plants need attention today.
        </p>
      </div>

      <Card className="p-4">
        <SectionTitle
          icon={Bell}
          action={
            <button onClick={() => go('/plants')} className="text-[12px] text-primary font-medium">
              All plants
            </button>
          }
        >
          Due for care
        </SectionTitle>
        {d.due_for_care.length === 0 ? (
          <EmptyState title="All caught up">No plants need care right now.</EmptyState>
        ) : (
          <div className="-mx-1">
            {d.due_for_care.map(item => (
              <DueRow
                key={item.plant_id + item.type}
                item={item}
                onClick={() => go(`/plants/${item.plant_id}`)}
              />
            ))}
          </div>
        )}
      </Card>

      <Card className="p-4">
        <SectionTitle icon={AlertTriangle}>Flagged problems</SectionTitle>
        {d.flagged_problems.length === 0 ? (
          <EmptyState title="Looking good">No flagged problems detected.</EmptyState>
        ) : (
          <div className="space-y-2">
            {d.flagged_problems.map((f, i) => (
              <button
                key={i}
                onClick={() => go(`/plants/${f.plant_id}`)}
                className="w-full text-left flex items-start gap-3 p-3 rounded-[8px] border border-border hover:bg-surface-raised transition-colors"
              >
                <span
                  className="mt-0.5 shrink-0"
                  style={{
                    color: f.severity === 'alert' ? 'var(--overdue)' : 'var(--due-soon)',
                  }}
                >
                  <AlertTriangle size={16} />
                </span>
                <div className="min-w-0">
                  <div className="font-medium flex items-center gap-2">
                    {f.common_name}
                    <span
                      className="text-[11px] font-normal px-1.5 py-0.5 rounded-full"
                      style={{
                        background: `color-mix(in srgb,${f.severity === 'alert' ? 'var(--overdue)' : 'var(--due-soon)'} 16%,transparent)`,
                        color: f.severity === 'alert' ? 'var(--overdue)' : 'var(--due-soon)',
                      }}
                    >
                      {f.severity}
                    </span>
                  </div>
                  <div className="text-[12px] text-text-muted mt-0.5">{f.problem}</div>
                </div>
              </button>
            ))}
          </div>
        )}
      </Card>

      <Card className="p-4">
        <SectionTitle icon={Clock}>Recent activity</SectionTitle>
        {d.recent_activity.length === 0 ? (
          <EmptyState title="No activity yet">
            Check back later for recent plant care logs.
          </EmptyState>
        ) : (
          <div className="space-y-1">
            {d.recent_activity.map(a => (
              <button
                key={a.event_id}
                onClick={() => go(`/plants/${a.plant_id}`)}
                className="w-full flex items-center gap-3 p-2 rounded-[8px] hover:bg-surface-raised text-left"
              >
                <TypeIcon type={a.type as CareType} size={14} />
                <div className="min-w-0 flex-1">
                  <span className="font-medium">{a.plant_common_name}</span>
                  <span className="text-text-muted">
                    {' · '}
                    {CARE_META[a.type as CareType].label.toLowerCase()}
                  </span>
                  {a.note && <div className="text-[12px] text-text-subtle truncate">{a.note}</div>}
                </div>
                <span className="text-[11px] text-text-subtle shrink-0 tnum">
                  {relDay(a.occurred_at)}
                </span>
              </button>
            ))}
          </div>
        )}
      </Card>
    </div>
  )
}
