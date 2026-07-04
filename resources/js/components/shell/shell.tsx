import { useState } from 'react'
import { Navigate, Route, Routes, useNavigate, useParams } from 'react-router-dom'
import { AppContext } from '@/components/app/app-context'
import { ErrorBanner } from '@/components/app/error-banner'
import { Modal } from '@/components/app/modal'
import { AddPlantForm } from '@/components/forms/add-plant-form'
import { CareLogProvider } from '@/components/plant/care-log-provider'
import { useIsMobile } from '@/hooks/useIsMobile'
import { useTheme } from '@/hooks/useTheme'
import api from '@/lib/api'
import { cn } from '@/lib/utils'
import { DashboardPage } from '@/pages/dashboard'
import { InsightsPage } from '@/pages/insights'
import { PlantDetailPage } from '@/pages/plant-detail'
import { PlantsPage } from '@/pages/plants'
import { SettingsPage } from '@/pages/settings'
import { MobileHeader } from './mobile-header'
import { TabBar } from './tab-bar'
import { TopBar } from './top-bar'

export function Shell() {
  const mobile = useIsMobile()
  const navigate = useNavigate()
  const { theme, setTheme } = useTheme()
  const [addOpen, setAddOpen] = useState(false)

  const go = (to: string) => navigate(to)
  const onLogout = async () => {
    try {
      await api.post('/logout')
    } finally {
      navigate('/login')
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
              <Route path="plants/:id" element={<PlantDetailRoute go={go} />} />
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
      </div>
    </AppContext.Provider>
  )
}

function PlantDetailRoute({ go }: { go: (to: string) => void }) {
  const { id } = useParams()
  const plantId = Number(id)
  return (
    <CareLogProvider plantId={plantId}>
      <PlantDetailPage id={plantId} go={go} />
    </CareLogProvider>
  )
}
