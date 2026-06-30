import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { Photo, PlantWithTags, Tag } from '@/api/types'
import { PlantsPage } from './plants'

vi.mock('@/hooks/usePlants', () => ({ usePlants: vi.fn() }))
vi.mock('@/hooks/useTags', () => ({ useTags: vi.fn() }))
import { usePlants } from '@/hooks/usePlants'
import { useTags } from '@/hooks/useTags'

const makePlant = (o: Partial<PlantWithTags>): PlantWithTags =>
  ({
    id: 1,
    common_name: 'Pothos',
    scientific_name: 'Epipremnum aureum',
    gbif_key: null,
    location: { id: 1, name: 'Shelf' },
    acquired_on: null,
    status: 'active',
    notes: null,
    watering_interval_days_override: null,
    fertilizing_interval_days_override: null,
    cover_photo_id: null,
    cover_photo: null,
    condition: { key: 'unknown', label: 'No reading' },
    created_at: '',
    updated_at: '',
    tags: [],
    due_for_care: [],
    last_watered_at: null,
    ...o,
  }) as PlantWithTags

const setPlants = (plants: PlantWithTags[], tags: Tag[] = []) => {
  vi.mocked(usePlants).mockReturnValue({ data: plants, loading: false, error: null })
  vi.mocked(useTags).mockReturnValue({ data: tags, loading: false, error: null })
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('PlantsPage', () => {
  it('renders the cover thumbnail and the derived condition from live data', () => {
    setPlants([makePlant({ cover_photo: { path: 'cover.jpg' } as Photo })])

    const { container } = render(<PlantsPage go={vi.fn()} onAdd={vi.fn()} />)

    // The cover thumbnail is decorative (the name sits beside it), so it carries
    // an empty alt and is queried directly rather than by the img role.
    expect(container.querySelector('img')).toHaveAttribute('src', '/uploads/cover.jpg')
    expect(screen.getByText('No reading')).toBeInTheDocument()
  })

  it('filters the list by the selected tag', async () => {
    const living: Tag = { id: 2, name: 'Tropical', color: null }
    const office: Tag = { id: 3, name: 'Office', color: null }
    setPlants(
      [
        makePlant({ id: 1, common_name: 'Pothos', tags: [living] }),
        makePlant({ id: 2, common_name: 'ZZ plant', tags: [office] }),
      ],
      [living, office]
    )

    render(<PlantsPage go={vi.fn()} onAdd={vi.fn()} />)
    expect(screen.getByText('ZZ plant')).toBeInTheDocument()

    await userEvent.selectOptions(screen.getByRole('combobox'), '2')

    expect(screen.getByText('Pothos')).toBeInTheDocument()
    expect(screen.queryByText('ZZ plant')).toBeNull()
  })

  it('searches case-insensitively across common and scientific names', async () => {
    setPlants([
      makePlant({ id: 1, common_name: 'Pothos', scientific_name: 'Epipremnum aureum' }),
      makePlant({ id: 2, common_name: 'Snake plant', scientific_name: 'Dracaena trifasciata' }),
    ])

    render(<PlantsPage go={vi.fn()} onAdd={vi.fn()} />)
    await userEvent.type(screen.getByPlaceholderText(/search name/i), 'DRACAENA')

    expect(screen.getByText('Snake plant')).toBeInTheDocument()
    expect(screen.queryByText('Pothos')).toBeNull()
  })

  it('shows the watering status from due_for_care data', () => {
    setPlants([
      makePlant({
        due_for_care: [
          {
            plant_id: 1,
            common_name: 'Pothos',
            scientific_name: 'Epipremnum aureum',
            status: 'ok',
            due_date: '2026-07-05',
            type: 'watering',
            daysLeft: 5,
            interval: 7,
          },
        ],
      }),
    ])

    render(<PlantsPage go={vi.fn()} onAdd={vi.fn()} />)

    expect(screen.getByText('Water in 5d')).toBeInTheDocument()
    expect(screen.queryByText('No watering logged')).toBeNull()
  })

  it('shows watered-ago text when last_watered_at exists but due_for_care is empty', () => {
    const twoDaysAgo = new Date(Date.now() - 2 * 86400000).toISOString()
    setPlants([
      makePlant({
        due_for_care: [],
        last_watered_at: twoDaysAgo,
      }),
    ])

    render(<PlantsPage go={vi.fn()} onAdd={vi.fn()} />)

    expect(screen.queryByText('No watering logged')).toBeNull()
    expect(screen.getByText(/watered/i)).toBeInTheDocument()
  })

  it('hides non-active plants until their status filter is toggled on', async () => {
    setPlants([
      makePlant({ id: 1, common_name: 'Living one', status: 'active' }),
      makePlant({ id: 2, common_name: 'Gone one', status: 'dead' }),
    ])

    render(<PlantsPage go={vi.fn()} onAdd={vi.fn()} />)
    expect(screen.queryByText('Gone one')).toBeNull()

    await userEvent.click(screen.getByRole('button', { name: /^dead$/i }))

    expect(screen.getByText('Gone one')).toBeInTheDocument()
  })
})
