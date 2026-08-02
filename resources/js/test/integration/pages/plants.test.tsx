import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import type { ReactNode } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { PlantsPage } from '@/pages/plants'
import { server } from '../../handlers'
import plantsEmptyFixture from '../../fixtures/plants/empty.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={qc}>{children}</QueryClientProvider>
  }
}

describe('PlantsPage', () => {
  it('renders real plant data from the default MSW handler', async () => {
    render(<PlantsPage go={vi.fn()} onAdd={vi.fn()} />, { wrapper: makeWrapper() })

    expect(await screen.findByText('Polkadot-plant')).toBeInTheDocument()
  })

  it('shows the real empty state when the plants list is empty', async () => {
    server.use(http.get('/api/plants', () => HttpResponse.json(plantsEmptyFixture)))

    render(<PlantsPage go={vi.fn()} onAdd={vi.fn()} />, { wrapper: makeWrapper() })

    expect(await screen.findByText('No plants match')).toBeInTheDocument()
  })

  it('filters the real list as the user types in the search box', async () => {
    render(<PlantsPage go={vi.fn()} onAdd={vi.fn()} />, { wrapper: makeWrapper() })

    const search = await screen.findByPlaceholderText(/search name/i)
    await userEvent.type(search, 'zz plant')

    expect(screen.getByText('ZZ Plant')).toBeInTheDocument()
    expect(screen.queryByText('Polkadot-plant')).toBeNull()
  })

  it('re-fetches plants when sort selection changes', async () => {
    const { container } = render(<PlantsPage go={vi.fn()} onAdd={vi.fn()} />, {
      wrapper: makeWrapper(),
    })

    expect(await screen.findByText('Polkadot-plant')).toBeInTheDocument()

    // Under the default last_watered:desc sort, baby rubberplant leads both
    // orderings, so the second card is what actually proves sorting happened.
    const cards = container.querySelectorAll('[dusk="plant-card"]')
    expect(cards[1]).toHaveTextContent("golden hunter's-robe")

    const sortSelect = container.querySelector('[dusk="plants-sort"]') as HTMLSelectElement
    await userEvent.selectOptions(sortSelect, 'name:asc')

    expect(await screen.findByText('Barbados aloe')).toBeInTheDocument()
    const resortedCards = container.querySelectorAll('[dusk="plant-card"]')
    expect(resortedCards[0]).toHaveTextContent('baby rubberplant')
    expect(resortedCards[1]).toHaveTextContent('Barbados aloe')
  })
})
