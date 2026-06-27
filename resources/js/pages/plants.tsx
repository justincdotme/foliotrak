import { Check, Leaf, MapPin, Plus, Search, Sprout } from 'lucide-react'
import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Chip } from '@/components/app/chip'
import { ConditionChip } from '@/components/app/condition-chip'
import { EmptyState } from '@/components/app/empty-state'
import { Spinner } from '@/components/app/spinner'
import { StatusPill } from '@/components/app/status-pill'
import { WaterDrop } from '@/components/app/water-drop'
import type { CareStatus, PlantWithTags } from '@/api/types'
import { photoUrl } from '@/lib/photos'
import { usePlants } from '@/hooks/usePlants'
import { useTags } from '@/hooks/useTags'

interface PlantsPageProps {
  go: (to: string) => void
  onAdd: () => void
}

// Water need is derived from logged waterings; until care logging exists the card
// shows the no-reading droplet.
type WaterNeed = { status: CareStatus; daysLeft: number; interval: number } | null

function waterLabel(due: WaterNeed) {
  if (!due) return { text: 'No watering logged', color: 'var(--text-subtle)' }
  if (due.status === 'overdue')
    return { text: `Water ${Math.abs(due.daysLeft)}d overdue`, color: 'var(--overdue)' }
  if (due.status === 'due-soon')
    return {
      text: due.daysLeft <= 0 ? 'Water today' : 'Water due soon',
      color: 'var(--due-soon)',
    }
  return { text: `Water in ${due.daysLeft}d`, color: 'var(--text-muted)' }
}

interface PlantCardProps {
  p: PlantWithTags
  onClick: () => void
}

function PlantCard({ p, onClick }: PlantCardProps) {
  const due: WaterNeed = null
  const cond = p.condition
  const wl = waterLabel(due)

  return (
    <button
      onClick={onClick}
      className="group flex flex-col text-left bg-surface border border-border rounded-[10px] overflow-hidden hover:border-border-strong transition-colors"
    >
      <div
        className="aspect-[4/3] relative grid place-items-center text-text-subtle overflow-hidden"
        style={
          p.cover_photo
            ? undefined
            : {
                backgroundImage:
                  'repeating-linear-gradient(135deg, color-mix(in srgb, var(--primary) 9%, transparent) 0 12px, transparent 12px 24px)',
              }
        }
      >
        {p.cover_photo ? (
          <img
            src={photoUrl(p.cover_photo.path)}
            alt=""
            loading="lazy"
            className="absolute inset-0 h-full w-full object-cover"
          />
        ) : (
          <Leaf size={28} />
        )}
        <div className="absolute top-2 left-2">
          <ConditionChip cond={cond} />
        </div>
        {p.status === 'active' ? (
          <div className="absolute top-2 right-2 bg-surface/85 backdrop-blur rounded-full p-0.5">
            <WaterDrop due={due} size={24} />
          </div>
        ) : (
          <div className="absolute top-2 right-2">
            <StatusPill status={p.status} />
          </div>
        )}
      </div>
      <div className="p-3">
        <div className="font-medium truncate">{p.common_name}</div>
        <div className="text-[12px] text-text-subtle italic truncate">{p.scientific_name}</div>
        <div className="flex items-center gap-1 text-[12px] text-text-muted mt-1.5">
          <MapPin size={12} />
          {p.location?.name || 'No location'}
        </div>
        <div className="flex flex-wrap gap-1 mt-2">
          {p.tags.map(t => (
            <Chip key={t.id} color={t.color || undefined}>
              {t.name}
            </Chip>
          ))}
        </div>
        {p.status === 'active' && (
          <div
            className="mt-2.5 pt-2.5 border-t border-border flex items-center gap-1.5 text-[12px] font-medium"
            style={{ color: wl.color }}
          >
            {wl.text}
          </div>
        )}
      </div>
    </button>
  )
}

export function PlantsPage({ go, onAdd }: PlantsPageProps) {
  const { data: plants, loading } = usePlants()
  const { data: tags } = useTags()
  const [q, setQ] = useState('')
  const [tagF, setTagF] = useState<number | null>(null)
  const [statusF, setStatusF] = useState<string[]>(['active'])

  if (loading) return <Spinner />

  const filtered = (plants || []).filter(
    p =>
      (!q ||
        (p.common_name + ' ' + (p.scientific_name || ''))
          .toLowerCase()
          .includes(q.toLowerCase())) &&
      (!tagF || p.tags.some(t => t.id === tagF)) &&
      statusF.includes(p.status)
  )

  const toggleStatus = (s: string) =>
    setStatusF(f => (f.includes(s) ? f.filter(x => x !== s) : [...f, s]))

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2">
        <h1 className="text-2xl font-semibold">Plants</h1>
        <span className="text-text-subtle tnum">{filtered.length}</span>
        <Button variant="accent" className="ml-auto" onClick={onAdd}>
          <Plus size={16} />
          Add plant
        </Button>
      </div>
      <div className="flex flex-wrap gap-2 items-center">
        <div className="relative flex-1 min-w-[180px]">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-text-subtle" />
          <Input
            value={q}
            onChange={e => setQ(e.target.value)}
            placeholder="Search name…"
            className="pl-9"
          />
        </div>
        <select
          value={(tagF ?? '') as string}
          onChange={e => setTagF(e.target.value ? Number(e.target.value) : null)}
          className="w-auto min-w-[140px] h-11 px-3 rounded-md bg-surface-raised border border-border-strong text-text placeholder:text-text-subtle focus:border-primary outline-none transition-colors"
        >
          <option value="">All tags</option>
          {(tags || []).map(t => (
            <option key={t.id} value={t.id}>
              {t.name}
            </option>
          ))}
        </select>
      </div>
      <div className="flex flex-wrap gap-1.5">
        {['active', 'archived', 'dead'].map(s => (
          <Chip key={s} active={statusF.includes(s)} onClick={() => toggleStatus(s)}>
            {statusF.includes(s) && <Check size={12} />}
            {s.charAt(0).toUpperCase() + s.slice(1)}
          </Chip>
        ))}
      </div>
      {filtered.length === 0 ? (
        <Card>
          <EmptyState icon={Sprout} title="No plants match">
            Try clearing filters, or add your first plant to get started.
          </EmptyState>
        </Card>
      ) : (
        <div
          className="grid gap-3"
          style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(170px, 1fr))' }}
        >
          {filtered.map(p => (
            <PlantCard key={p.id} p={p} onClick={() => go('/plants/' + p.id)} />
          ))}
        </div>
      )}
    </div>
  )
}
