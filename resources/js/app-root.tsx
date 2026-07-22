import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { NotificationProvider } from '@/components/app/notification-provider'
import { AuthGate } from '@/components/shell/auth-gate'
import { Shell } from '@/components/shell/shell'
import { TooltipProvider } from '@/components/ui/tooltip'
import { LoginPage } from '@/pages/login'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { staleTime: 1000 * 60 * 5, gcTime: 1000 * 60 * 10, retry: false },
  },
})

export function AppRoot() {
  return (
    <QueryClientProvider client={queryClient}>
      <NotificationProvider>
        {/* At the root, not in Shell: the login route renders outside the shell
            and its submit button mounts a tooltip while pending. */}
        <TooltipProvider delayDuration={400}>
          <BrowserRouter>
            <Routes>
              <Route path="/login" element={<LoginPage />} />
              <Route
                path="/*"
                element={
                  <AuthGate>
                    <Shell />
                  </AuthGate>
                }
              />
            </Routes>
          </BrowserRouter>
        </TooltipProvider>
      </NotificationProvider>
    </QueryClientProvider>
  )
}
