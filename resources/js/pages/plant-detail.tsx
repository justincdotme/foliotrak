import {
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
  Settings2,
  Shovel,
  Sprout,
} from 'lucide-react'
import { useMemo, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import type { CareEvent, CareType, Photo } from '@/api/types'
import { updatePlant } from '@/api/client'
import { useEquipment } from '@/hooks/useEquipment'
import { usePlant } from '@/hooks/usePlant'
import { usePlantPhotos } from '@/hooks/usePlantPhotos'
import { useTimeline } from '@/hooks/useTimeline'
import { useRecommendations } from '@/hooks/useRecommendations'
import { useCareEventMutations } from '@/hooks/useCareEventMutations'
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
import { ActivityHeatmap } from '@/components/charts/activity-heatmap'
import { GrowthTrend } from '@/components/charts/growth-trend'
import { HealthByLocation } from '@/components/charts/health-by-location'
import { HealthTrend } from '@/components/charts/health-trend'
import { TimelineOverlay } from '@/components/charts/timeline-overlay'
import { WeightTrend } from '@/components/charts/weight-trend'
import { fmtDate, fmtDateY } from '@/lib/format'
import { groupPhotosByCareEvent, photoUrl } from '@/lib/photos'
import { EditPlantModal } from '@/components/plant/edit-plant-modal'
import { PrimaryPhotoModal } from '@/components/plant/primary-photo-modal'
import { ScheduleSection } from '@/components/plant/schedule-section'
import { TimelineItem } from '@/components/plant/timeline-item'

interface PlantDetailPageProps {
  id: number
  go: (to: string) => void
  openLog: (type: CareType, event?: CareEvent) => void
  viewPhoto: (photo: Photo) => void
}

export function PlantDetailPage({ id, go, openLog, viewPhoto }: PlantDetailPageProps) {
  const { data: plant, loading } = usePlant(id)
  const { data: photos } = usePlantPhotos(id)
  const { data: timeline } = useTimeline(id)
  const {
    data: recommendations,
    loading: recommendationsLoading,
    error: recommendationsError,
  } = useRecommendations(id)
  const { deleteEvent } = useCareEventMutations(id)
  const { data: allEquipment } = useEquipment()
  const queryClient = useQueryClient()
  const [editOpen, setEditOpen] = useState(false)
  const [photoOpen, setPhotoOpen] = useState(false)

  const events = timeline?.events ?? []
  const photosByEvent = useMemo(() => groupPhotosByCareEvent(photos ?? []), [photos])

  if (loading) return <Spinner />

  if (!plant)
    return (
      <Card>
        <EmptyState icon={Sprout} title="Plant not found">
          It may have been deleted.
        </EmptyState>
      </Card>
    )

  // The timeline bundle carries the trend series; the watering-due signal arrives with a later
  // per-plant due feature, so it stays null here.
  const healthTrend = timeline?.health_trend ?? []
  const weightTrend = timeline?.weight_trend ?? []
  const growthTrend = timeline?.growth_trend ?? []
  const photoList = photos ?? []

  // Health-by-location reads as a comparison only with two or more spots; a single spot adds
  // nothing the health trend does not already show.
  const locationBuckets = (recommendations?.health_by_location ?? []).filter(l => l.sample_size > 0)

  // Each observation-derived series is charted only when it holds a real value;
  // overall_health is optional, so a length check alone would draw an empty line.
  const hasHealth = healthTrend.some(p => p.value != null)
  const hasWeight = weightTrend.some(p => p.value != null)
  const hasGrowth = growthTrend.some(p => p.value != null)

  const due = null
  const cond = plant.condition

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
            className="w-20 h-20 rounded-[10px] grid place-items-center text-text-subtle border border-border overflow-hidden"
            style={
              plant.cover_photo
                ? undefined
                : {
                    backgroundImage:
                      'repeating-linear-gradient(135deg, color-mix(in srgb,var(--primary) 9%,transparent) 0 10px, transparent 10px 20px)',
                  }
            }
          >
            {plant.cover_photo ? (
              <img
                src={photoUrl(plant.cover_photo.path)}
                alt=""
                className="h-full w-full object-cover"
              />
            ) : (
              <Leaf size={26} />
            )}
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
              {plant.location?.name || 'No location'}
            </span>
            {plant.acquired_on && (
              <span className="flex items-center gap-1">
                <Calendar size={12} />
                Since {fmtDateY(plant.acquired_on)}
              </span>
            )}
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

      {/* Equipment near this plant */}
      <Card className="p-4">
        <SectionTitle icon={Settings2}>Equipment</SectionTitle>
        <div className="mt-2 flex flex-wrap gap-1.5">
          {allEquipment.map(eq => {
            const active = plant.equipment?.some(e => e.id === eq.id) ?? false
            return (
              <Chip
                key={eq.id}
                active={active}
                outline={!active}
                color="var(--primary)"
                onClick={async () => {
                  const current = plant.equipment?.map(e => e.id) ?? []
                  const next = active ? current.filter(x => x !== eq.id) : [...current, eq.id]
                  await updatePlant(id, { equipment_ids: next })
                  queryClient.invalidateQueries({ queryKey: ['plant', id] })
                  queryClient.invalidateQueries({ queryKey: ['plants'] })
                }}
              >
                {eq.label}
              </Chip>
            )
          })}
        </div>
        {allEquipment.length === 0 && (
          <p className="text-[13px] text-text-muted mt-1">No equipment options available.</p>
        )}
      </Card>

      {/* Schedule (My schedule / Recommended) */}
      <ScheduleSection
        plant={plant}
        recommendations={recommendations}
        recommendationsLoading={recommendationsLoading}
        recommendationsError={!!recommendationsError}
        due={due}
      />

      <EditPlantModal plant={plant} open={editOpen} onClose={() => setEditOpen(false)} />
      <PrimaryPhotoModal
        plant={plant}
        photos={photoList}
        open={photoOpen}
        onClose={() => setPhotoOpen(false)}
      />

      {/* Charts */}
      {events.length === 0 ? (
        <Card>
          <EmptyState icon={BarChart3} title="Nothing to chart yet">
            Log a watering or observation to start charting activity, health, weight, and growth.
          </EmptyState>
        </Card>
      ) : (
        <div className="space-y-4">
          {hasHealth && <TimelineOverlay health={healthTrend} events={events} />}
          <div
            className="grid gap-4"
            style={{ gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))' }}
          >
            {hasHealth && <HealthTrend data={healthTrend} />}
            {hasWeight && <WeightTrend data={weightTrend} />}
            {hasGrowth && <GrowthTrend data={growthTrend} />}
            <ActivityHeatmap events={events} />
          </div>
        </div>
      )}

      {/* Health by location (did moving help) */}
      {locationBuckets.length >= 2 && <HealthByLocation data={locationBuckets} />}

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
        {photoList.length === 0 ? (
          <EmptyState icon={ImageIcon} title="No photos yet">
            Add a photo when you log an observation.
          </EmptyState>
        ) : (
          <div className="grid grid-cols-3 gap-2">
            {photoList.map(ph => (
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
              <TimelineItem
                key={e.id}
                e={e}
                photos={photosByEvent[e.id] ?? []}
                onEdit={() => openLog(e.type, e)}
                onViewPhoto={viewPhoto}
                onDelete={() => deleteEvent.mutateAsync(e.id)}
              />
            ))}
          </div>
        )}
      </Card>
    </div>
  )
}
