import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import type { ReactNode } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { DashboardPage } from '@/pages/dashboard'
import { server } from '../../handlers'
import dashboardFixture from '../../fixtures/dashboard/populated.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={qc}>{children}</QueryClientProvider>
  }
}

describe('DashboardPage', () => {
  it('renders real due-for-care and recent-activity data from the populated fixture', async () => {
    render(<DashboardPage go={vi.fn()} />, { wrapper: makeWrapper() })

    expect(await screen.findByText('in 5d')).toBeInTheDocument()
    expect(screen.getByText("golden hunter's-robe")).toBeInTheDocument()
    expect(screen.getByText('0 plants need attention today.')).toBeInTheDocument()
  })

  it('shows the caught-up empty state when due_for_care is overridden to empty', async () => {
    server.use(
      http.get('/api/dashboard', () =>
        HttpResponse.json({ data: { ...dashboardFixture.data, due_for_care: [] } })
      )
    )

    render(<DashboardPage go={vi.fn()} />, { wrapper: makeWrapper() })

    expect(await screen.findByText('All caught up')).toBeInTheDocument()
  })
})
