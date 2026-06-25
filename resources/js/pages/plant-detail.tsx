import {
  AlertTriangle,
  BarChart3,
  Calendar,
  Camera,
  ChevronLeft,
  Clock,
  ClipboardList,
  Droplets,
  ExternalLink,
  FlaskConical,
  ImageIcon,
  Leaf,
  MapPin,
  Pencil,
  Plus,
  Shovel,
  Sprout,
} from 'lucide-react'
import { useState } from 'react'
import type { CareType, Photo } from '@/api/types'
import { mockApi, nextDue, plantCondition } from '@/api/mock'
import { useTimeline } from '@/hooks/useTimeline'
import { commit } from '@/hooks/useAsync'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { ConditionChip } from '@/components/app/condition-chip'
import { Chip } from '@/components/app/chip'
import { EmptyState } from '@/components/app/empty-state'
import { IconButton } from '@/components/app/icon-button'
import { PhotoTile } from '@/components/app/photo-tile'
import { SectionTitle } from '@/components/app/section-title'
import { Spinner } from '@/components/app/spinner'
import { StatusPill } from '@/components/app/status-pill'
import { WaterDrop } from '@/components/app/water-drop'
import { ActivityHeatmap } from '@/components/charts/activity-heatmap'
import { GrowthTrend } from '@/components/charts/growth-trend'
import { HealthTrend } from '@/components/charts/health-trend'
import { TimelineOverlay } from '@/components/charts/timeline-overlay'
import { WeightTrend } from '@/components/charts/weight-trend'
import { fmtDate, fmtDateY } from '@/lib/format'
import { EditPlantModal } from '@/components/plant/edit-plant-modal'
import { PrimaryPhotoModal } from '@/components/plant/primary-photo-modal'
import { ScheduleSection } from '@/components/plant/schedule-section'
import { TimelineItem } from '@/components/plant/timeline-item'

interface PlantDetailPageProps {
  id: number
  go: (to: string) => void
  openLog: (type: CareType) => void
  viewPhoto: (photo: Photo) => void
}

export function PlantDetailPage({ id, go, openLog, viewPhoto }: PlantDetailPageProps) {
  const { data, loading } = useTimeline(id)
  const [editOpen, setEditOpen] = useState(false)
  const [photoOpen, setPhotoOpen] = useState(false)

  if (loading) return <Spinner />

  if (!data || !data.plant)
    return (
      <Card>
        <EmptyState icon={Sprout} title="Plant not found">
          It may have been deleted.
        </EmptyState>
      </Card>
    )

  const { plant, events, health_trend, weight_trend, growth_trend, recommendations, photos } = data

  const del = async (eid: number) => {
    await mockApi.deleteCareEvent(eid)
    commit()
  }

  const hasObs = health_trend.length > 0 || weight_trend.length > 0
  const due = plant.status === 'active' ? nextDue(plant) : null
  const cond = plantCondition(plant)

  return (
    <div className="space-y-6">
      <button
        onClick={() => go('/plants')}
        className="inline-flex items-center gap-1 text-[13px] text-text-muted hover:text-text"
      >
        <ChevronLeft size={16} />
        All plants
      </button>

      {/* Header */}
      <div className="flex gap-4 items-start">
        <div className="relative shrink-0">
          <div
            className="w-20 h-20 rounded-[10px] grid place-items-center text-text-subtle border border-border"
            style={{
              backgroundImage:
                'repeating-linear-gradient(135deg, color-mix(in srgb,var(--primary) 9%,transparent) 0 10px, transparent 10px 20px)',
            }}
          >
            <Leaf size={26} />
          </div>
          <button
            onClick={() => setPhotoOpen(true)}
            aria-label="Change cover photo"
            title="Change cover photo"
            className="absolute -bottom-1.5 -right-1.5 w-7 h-7 rounded-full bg-surface border border-border-strong grid place-items-center text-text-muted hover:text-text shadow-sm"
          >
            <Camera size={14} />
          </button>
        </div>
        <div className="min-w-0 flex-1">
          <div className="flex items-start gap-2">
            <div className="min-w-0">
              <h1 className="text-xl font-semibold truncate">{plant.common_name}</h1>
              <div className="italic text-text-muted text-[13px] truncate">
                {plant.scientific_name}
              </div>
            </div>
            <IconButton
              label="Edit plant"
              onClick={() => setEditOpen(true)}
              className="ml-auto h-9 w-9 shrink-0"
            >
              <Pencil size={15} />
            </IconButton>
          </div>
          <div className="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2 text-[12px] text-text-muted">
            <span className="flex items-center gap-1">
              <MapPin size={12} />
              {plant.location || 'No location'}
            </span>
            <span className="flex items-center gap-1">
              <Calendar size={12} />
              Since {fmtDateY(plant.acquired_on || '')}
            </span>
            {plant.gbif_key && (
              <a
                href={`https://www.gbif.org/species/${plant.gbif_key}`}
                target="_blank"
                rel="noreferrer"
                className="flex items-center gap-1 text-primary hover:underline"
              >
                GBIF <ExternalLink size={11} />
              </a>
            )}
          </div>
          <div className="flex flex-wrap gap-1.5 mt-2">
            <ConditionChip cond={cond} />
            {plant.status !== 'active' && <StatusPill status={plant.status} />}
            {plant.tags.map(t => (
              <Chip key={t.id} color={t.color || undefined}>
                {t.name}
              </Chip>
            ))}
          </div>
        </div>
      </div>

      {plant.notes && <p className="text-[13px] text-text-muted">{plant.notes}</p>}

      {/* Overdue highlight: surfaced near the top */}
      {due && due.status === 'overdue' && (
        <button
          onClick={() => openLog('watering')}
          className="w-full text-left rounded-card p-3.5 flex items-center gap-3"
          style={{
            background: 'color-mix(in srgb,var(--overdue) 12%,transparent)',
            border: '1px solid color-mix(in srgb,var(--overdue) 38%,transparent)',
          }}
        >
          <WaterDrop due={due} size={34} />
          <div className="min-w-0 flex-1">
            <div
              className="font-semibold flex items-center gap-1.5"
              style={{ color: 'var(--overdue)' }}
            >
              <AlertTriangle size={16} />
              Overdue for water by {Math.abs(due.daysLeft)} day
              {Math.abs(due.daysLeft) === 1 ? '' : 's'}
            </div>
            <div className="text-[12px] text-text-muted">Was due {fmtDateY(due.due_date)}.</div>
          </div>
          <span
            className="shrink-0 inline-flex items-center gap-1.5 h-9 px-3 rounded-[8px] text-[13px] font-medium text-white"
            style={{ background: 'var(--overdue)' }}
          >
            <Droplets size={15} />
            Log water
          </span>
        </button>
      )}

      {/* Log actions */}
      <div className="grid grid-cols-2 gap-2">
        <Button variant="outline" onClick={() => openLog('watering')}>
          <Droplets size={16} className="text-info" />
          Water
        </Button>
        <Button variant="outline" onClick={() => openLog('fertilizing')}>
          <FlaskConical size={16} className="text-accent" />
          Fertilize
        </Button>
        <Button variant="outline" onClick={() => openLog('repotting')}>
          <Shovel size={16} style={{ color: 'var(--series-4)' }} />
          Repot
        </Button>
        <Button variant="outline" onClick={() => openLog('observation')}>
          <ClipboardList size={16} className="text-primary" />
          Observe
        </Button>
      </div>

      {/* Schedule (My schedule / Recommended) */}
      <ScheduleSection plant={plant} recs={recommendations} due={due} events={events} />

      <EditPlantModal plant={plant} open={editOpen} onClose={() => setEditOpen(false)} />
      <PrimaryPhotoModal plant={plant} open={photoOpen} onClose={() => setPhotoOpen(false)} />

      {/* Charts */}
      {hasObs ? (
        <div className="space-y-4">
          <TimelineOverlay health={health_trend} events={events} />
          <div
            className="grid gap-4"
            style={{ gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))' }}
          >
            {health_trend.length > 0 && <HealthTrend data={health_trend} />}
            {weight_trend.length > 0 && <WeightTrend data={weight_trend} />}
            {growth_trend.length > 0 && <GrowthTrend data={growth_trend} />}
            <ActivityHeatmap events={events} />
          </div>
        </div>
      ) : (
        <Card>
          <EmptyState icon={BarChart3} title="No observations yet">
            Log an observation to start charting health, weight, and growth.
          </EmptyState>
        </Card>
      )}

      {/* Photos */}
      <Card className="p-4">
        <SectionTitle
          icon={ImageIcon}
          action={
            <Button size="sm" variant="ghost" onClick={() => openLog('observation')}>
              <Plus size={14} />
              Add
            </Button>
          }
        >
          Photos
        </SectionTitle>
        {photos.length === 0 ? (
          <EmptyState icon={ImageIcon} title="No photos yet">
            Add a photo when you log an observation.
          </EmptyState>
        ) : (
          <div className="grid grid-cols-3 gap-2">
            {photos.map(ph => (
              <div key={ph.id}>
                <PhotoTile
                  photo={ph}
                  className="aspect-square w-full"
                  onClick={() => viewPhoto(ph)}
                />
                <div className="text-[11px] text-text-subtle mt-1 tnum">{fmtDate(ph.taken_on)}</div>
              </div>
            ))}
          </div>
        )}
      </Card>

      {/* Timeline */}
      <Card className="p-4">
        <SectionTitle icon={Clock}>Care timeline</SectionTitle>
        {events.length === 0 ? (
          <EmptyState icon={Clock} title="No care events logged yet">
            Start by logging a watering or observation.
          </EmptyState>
        ) : (
          <div className="mt-1">
            {events.map(e => (
              <TimelineItem key={e.id} e={e} onDelete={del} />
            ))}
          </div>
        )}
      </Card>
    </div>
  )
}
