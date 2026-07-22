import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import type { ReactNode } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { PlantDetailPage } from '@/pages/plant-detail'
import { ErrorBanner } from '@/components/app/error-banner'
import { NotificationProvider } from '@/components/app/notification-provider'
import { server } from '../../handlers'

// Recharts/Nivo render real SVG in jsdom; stubbed here so the test exercises real
// hooks and MSW data without paying for chart layout math.
vi.mock('@/components/charts/activity-heatmap', () => ({
  ActivityHeatmap: () => <div data-testid="heatmap" />,
}))
vi.mock('@/components/charts/growth-trend', () => ({
  GrowthTrend: () => <div data-testid="growth" />,
}))
vi.mock('@/components/charts/health-by-location', () => ({
  HealthByLocation: () => <div data-testid="locations" />,
}))
vi.mock('@/components/charts/health-trend', () => ({
  HealthTrend: () => <div data-testid="health" />,
}))
vi.mock('@/components/charts/leaf-size-trend', () => ({
  LeafSizeTrend: () => <div data-testid="leaf-size" />,
}))
vi.mock('@/components/charts/light-trend', () => ({
  LightTrend: () => <div data-testid="light" />,
}))
vi.mock('@/components/charts/timeline-overlay', () => ({
  TimelineOverlay: () => <div data-testid="overlay" />,
}))
vi.mock('@/components/charts/weight-trend', () => ({
  WeightTrend: () => <div data-testid="weight" />,
}))

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={qc}>
        <NotificationProvider>
          <ErrorBanner />
          {children}
        </NotificationProvider>
      </QueryClientProvider>
    )
  }
}

const renderPage = () =>
  render(<PlantDetailPage id={3} go={vi.fn()} />, {
    wrapper: makeWrapper(),
  })

describe('PlantDetailPage', () => {
  it('loads and shows the real plant from plants/detail-1.json', async () => {
    renderPage()

    expect(
      await screen.findByRole('heading', { name: 'Tradescantia sillamontana' })
    ).toBeInTheDocument()
  })

  it('renders both real heterogeneous timeline events, including their type-specific detail', async () => {
    const user = userEvent.setup()
    renderPage()

    const relocationToggle = await screen.findByRole('button', { name: /moved/i })
    const observationToggle = screen.getByRole('button', { name: /observation/i })

    await user.click(relocationToggle)
    expect(screen.getByText('Shelf A')).toBeInTheDocument()

    await user.click(observationToggle)
    expect(screen.getByText('Showing some stress on lower leaves.')).toBeInTheDocument()
  })

  it('deletes a timeline event through the real client and the MSW delete handler', async () => {
    const user = userEvent.setup()
    const deleteSpy = vi.fn()
    server.use(
      http.delete('/api/care-events/:id', ({ params }) => {
        deleteSpy(params.id)
        return new HttpResponse(null, { status: 204 })
      })
    )
    renderPage()

    const relocationToggle = await screen.findByRole('button', { name: /moved/i })
    await user.click(relocationToggle)
    await user.click(screen.getByRole('button', { name: /^delete$/i }))

    const dialog = screen.getByRole('dialog')
    await user.click(within(dialog).getByRole('button', { name: /delete/i }))

    await waitFor(() => expect(deleteSpy).toHaveBeenCalledWith('41'))
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })
})
