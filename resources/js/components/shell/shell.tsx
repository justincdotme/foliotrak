import { useState } from 'react'
import { Navigate, Route, Routes, useNavigate, useParams } from 'react-router-dom'
import type { CareEvent, CareType, Photo } from '@/api/types'
import { AppContext } from '@/components/app/app-context'
import { ErrorBanner } from '@/components/app/error-banner'
import { Modal } from '@/components/app/modal'
import { PhotoTile } from '@/components/app/photo-tile'
import { AddPlantForm } from '@/components/forms/add-plant-form'
import { LogFertilizingForm } from '@/components/forms/log-fertilizing-form'
import { LogObservationForm } from '@/components/forms/log-observation-form'
import { LogRepottingForm } from '@/components/forms/log-repotting-form'
import { LogWateringForm } from '@/components/forms/log-watering-form'
import { RelocationEditForm } from '@/components/forms/relocation-edit-form'
import { useIsMobile } from '@/hooks/useIsMobile'
import { useTheme } from '@/hooks/useTheme'
import api from '@/lib/api'
import { fmtDateY } from '@/lib/format'
import { cn } from '@/lib/utils'
import { DashboardPage } from '@/pages/dashboard'
import { InsightsPage } from '@/pages/insights'
import { PlantDetailPage } from '@/pages/plant-detail'
import { PlantsPage } from '@/pages/plants'
import { SettingsPage } from '@/pages/settings'
import { MobileHeader } from './mobile-header'
import { TabBar } from './tab-bar'
import { TopBar } from './top-bar'

const LOG_TITLES: Record<CareType, string> = {
  watering: 'Log watering',
  fertilizing: 'Log fertilizing',
  repotting: 'Log repotting',
  observation: 'Log observation',
  relocation: 'Log relocation',
}

const EDIT_TITLES: Record<CareType, string> = {
  watering: 'Edit watering',
  fertilizing: 'Edit fertilizing',
  repotting: 'Edit repotting',
  observation: 'Edit observation',
  relocation: 'Edit move',
}

interface LogTarget {
  plantId: number
  type: CareType
  event?: CareEvent
  seedOccurredAt?: string
}

export function Shell() {
  const mobile = useIsMobile()
  const navigate = useNavigate()
  const { theme, setTheme } = useTheme()
  const [addOpen, setAddOpen] = useState(false)
  const [logFor, setLogFor] = useState<LogTarget | null>(null)
  const [photo, setPhoto] = useState<Photo | null>(null)

  const go = (to: string) => navigate(to)
  const onLogout = async () => {
    try {
      await api.post('/logout')
    } finally {
      navigate('/login')
    }
  }
  const openLog = (plantId: number) => (type: CareType, event?: CareEvent) =>
    setLogFor({ plantId, type, event })

  const renderLogForm = (target: LogTarget) => {
    const close = () => setLogFor(null)
    switch (target.type) {
      case 'watering':
        return <LogWateringForm plantId={target.plantId} event={target.event} onDone={close} />
      case 'fertilizing':
        return (
          <LogFertilizingForm
            plantId={target.plantId}
            event={target.event}
            seedOccurredAt={target.seedOccurredAt}
            onDone={close}
          />
        )
      case 'repotting':
        return (
          <LogRepottingForm
            plantId={target.plantId}
            event={target.event}
            onDone={close}
            onLogFertilizer={iso =>
              setLogFor({ plantId: target.plantId, type: 'fertilizing', seedOccurredAt: iso })
            }
          />
        )
      case 'observation':
        return <LogObservationForm plantId={target.plantId} event={target.event} onDone={close} />
      case 'relocation':
        return target.event ? (
          <RelocationEditForm plantId={target.plantId} event={target.event} onDone={close} />
        ) : null
    }
  }

  return (
    <AppContext.Provider value={{ mobile }}>
      <div dusk="app-shell" className="relative flex h-full flex-col bg-bg">
        {mobile ? (
          <MobileHeader onAdd={() => setAddOpen(true)} onLogout={onLogout} />
        ) : (
          <TopBar onAdd={() => setAddOpen(true)} onLogout={onLogout} />
        )}
        <ErrorBanner />
        <main
          className={cn(
            'flex-1 overflow-x-hidden overflow-y-auto',
            mobile ? 'px-4 py-4' : 'px-5 py-7'
          )}
        >
          <div className={mobile ? '' : 'mx-auto max-w-[1200px]'}>
            <Routes>
              <Route index element={<DashboardPage go={go} />} />
              <Route
                path="plants"
                element={<PlantsPage go={go} onAdd={() => setAddOpen(true)} />}
              />
              <Route
                path="plants/:id"
                element={<PlantDetailRoute go={go} openLog={openLog} viewPhoto={setPhoto} />}
              />
              <Route path="insights" element={<InsightsPage />} />
              <Route
                path="settings"
                element={<SettingsPage theme={theme} setTheme={setTheme} onLogout={onLogout} />}
              />
              <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
          </div>
        </main>
        {mobile && <TabBar />}

        <Modal
          open={addOpen}
          onClose={() => setAddOpen(false)}
          title="Add a plant"
          subtitle="Search a species or keep your own name."
          wide={!mobile}
          dusk="add-plant-modal"
        >
          <AddPlantForm onDone={() => setAddOpen(false)} />
        </Modal>

        <Modal
          open={!!logFor}
          onClose={() => setLogFor(null)}
          title={logFor ? (logFor.event ? EDIT_TITLES[logFor.type] : LOG_TITLES[logFor.type]) : ''}
          wide={logFor?.type === 'observation' || logFor?.type === 'fertilizing'}
          dusk="log-modal"
        >
          {logFor && renderLogForm(logFor)}
        </Modal>

        <Modal
          open={!!photo}
          onClose={() => setPhoto(null)}
          title={photo?.caption || 'Photo'}
          subtitle={photo ? fmtDateY(photo.taken_on) : ''}
        >
          {photo && <PhotoTile photo={photo} className="aspect-[4/3] w-full" />}
          {photo?.original_filename && (
            <div className="tnum mt-2 text-[12px] text-text-subtle">{photo.original_filename}</div>
          )}
        </Modal>
      </div>
    </AppContext.Provider>
  )
}

interface PlantDetailRouteProps {
  go: (to: string) => void
  openLog: (plantId: number) => (type: CareType, event?: CareEvent) => void
  viewPhoto: (photo: Photo) => void
}

function PlantDetailRoute({ go, openLog, viewPhoto }: PlantDetailRouteProps) {
  const { id } = useParams()
  const plantId = Number(id)
  return <PlantDetailPage id={plantId} go={go} openLog={openLog(plantId)} viewPhoto={viewPhoto} />
}
