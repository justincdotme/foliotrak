import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { InsightsPage } from '@/pages/insights'
import tagsFixture from '../../fixtures/lookups/tags.json'
import locationsFixture from '../../fixtures/lookups/locations.json'

// Recharts/Nivo render real SVG in jsdom; stubbed here so the test exercises real
// hooks and MSW data without paying for chart layout math.
vi.mock('@/components/charts/group-comparison', () => ({
  GroupComparison: () => <div data-testid="group-comparison" />,
}))
vi.mock('@/components/charts/correlation-scatter', () => ({
  CorrelationScatter: () => <div data-testid="corr-scatter" />,
}))
vi.mock('@/components/charts/correlation-heatmap', () => ({
  CorrelationHeatmap: () => <div data-testid="corr-heatmap" />,
}))

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={qc}>{children}</QueryClientProvider>
  }
}

const tagName = tagsFixture.data[0]?.name ?? ''
const locationName = locationsFixture.data[0]?.name ?? ''

describe('InsightsPage', () => {
  it('states the correlational, never causal framing', () => {
    render(<InsightsPage />, { wrapper: makeWrapper() })

    expect(screen.getByText(/correlational, never causal/i)).toBeInTheDocument()
  })

  it('renders real tag and location chips from the lookup fixtures', async () => {
    render(<InsightsPage />, { wrapper: makeWrapper() })

    expect(await screen.findByText(tagName)).toBeInTheDocument()
    expect(screen.getByText(locationName)).toBeInTheDocument()
  })

  it('toggles a tag chip to active on click without erroring the page', async () => {
    render(<InsightsPage />, { wrapper: makeWrapper() })

    const chip = await screen.findByText(tagName)
    const styleBeforeClick = chip.getAttribute('style')

    await userEvent.click(chip)

    expect(chip.getAttribute('style')).not.toBe(styleBeforeClick)
    expect(screen.getByText(/correlational, never causal/i)).toBeInTheDocument()
  })
})
