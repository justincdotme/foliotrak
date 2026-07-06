import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { CareEvent } from '@/api/types'
import { LogFertilizingForm } from './log-fertilizing-form'
import { TooltipProvider } from '@/components/ui/tooltip'

const renderWithProvider = (ui: React.ReactElement) =>
  render(<TooltipProvider>{ui}</TooltipProvider>)

const organicEvent: CareEvent = {
  id: 88,
  plant_id: 2,
  care_event_type_id: 2,
  type: 'fertilizing',
  occurred_at: '2026-06-15T09:15:00.000Z',
  logged_by_user_id: 1,
  note: null,
  created_at: '2026-06-15T09:15:00.000Z',
  updated_at: '2026-06-15T09:15:00.000Z',
  fertilizing: {
    care_event_id: 88,
    fertilizer_form_id: 4,
    form: 'organic',
    brand: "Neptune's",
    product: 'Fish & Seaweed',
    npk_n: null,
    npk_p: null,
    npk_k: null,
    dose_pct: 50,
    amount_ml: 300,
    nutrients: [
      {
        nutrient_id: 1,
        nutrient_key: 'kelp',
        nutrient_label: 'Kelp',
        nutrient_symbol: null,
        note: null,
      },
    ],
  },
}

vi.mock('@/hooks/useCareLookups', () => ({
  useFertilizerForms: () => ({
    data: [
      { id: 1, key: 'liquid', label: 'Liquid', sort_order: 1 },
      { id: 4, key: 'organic', label: 'Organic', sort_order: 4 },
    ],
    loading: false,
    error: null,
  }),
  useNutrients: () => ({
    data: [{ nutrient_id: 1, nutrient_key: 'kelp', nutrient_label: 'Kelp', nutrient_symbol: null }],
    loading: false,
    error: null,
  }),
}))

vi.mock('@/hooks/useCareEventMutations', () => ({ useCareEventMutations: vi.fn() }))
import { useCareEventMutations } from '@/hooks/useCareEventMutations'

const createFertilizing = { mutateAsync: vi.fn() }
const updateEvent = { mutateAsync: vi.fn() }

beforeEach(() => {
  vi.clearAllMocks()
  createFertilizing.mutateAsync.mockResolvedValue({ id: 7 })
  updateEvent.mutateAsync.mockResolvedValue({ id: 88 })
  vi.mocked(useCareEventMutations).mockReturnValue({
    createWatering: { mutateAsync: vi.fn() },
    createFertilizing,
    createRepotting: { mutateAsync: vi.fn() },
    createObservation: { mutateAsync: vi.fn() },
    updateEvent,
    deleteEvent: { mutateAsync: vi.fn() },
    uploadEventPhoto: { mutateAsync: vi.fn() },
  } as unknown as ReturnType<typeof useCareEventMutations>)
})

describe('LogFertilizingForm', () => {
  it('includes nutrient components only when the form is organic (detected by key)', async () => {
    renderWithProvider(<LogFertilizingForm plantId={2} onDone={vi.fn()} />)

    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Form' }), '4')
    await userEvent.click(screen.getByRole('button', { name: /^Add$/ }))
    await userEvent.click(screen.getByRole('button', { name: /Log fertilizing/ }))

    await waitFor(() =>
      expect(createFertilizing.mutateAsync).toHaveBeenCalledWith(
        expect.objectContaining({
          fertilizer_form_id: 4,
          nutrients: [{ nutrient_id: 1, note: null }],
        })
      )
    )
  })

  it('clears nutrients for a non-organic form', async () => {
    renderWithProvider(<LogFertilizingForm plantId={2} onDone={vi.fn()} />)

    // Default is liquid; submit without touching the form select.
    await userEvent.click(screen.getByRole('button', { name: /Log fertilizing/ }))

    await waitFor(() =>
      expect(createFertilizing.mutateAsync).toHaveBeenCalledWith(
        expect.objectContaining({ fertilizer_form_id: 1, nutrients: [] })
      )
    )
  })

  it('edits an organic event without auto-defaulting the form, routing to update', async () => {
    renderWithProvider(<LogFertilizingForm plantId={2} onDone={vi.fn()} event={organicEvent} />)

    // The auto-default-to-liquid effect must not fire on edit.
    expect(screen.getByRole('combobox', { name: 'Form' })).toHaveValue('4')

    await userEvent.click(screen.getByRole('button', { name: /Save changes/ }))

    await waitFor(() =>
      expect(updateEvent.mutateAsync).toHaveBeenCalledWith(
        expect.objectContaining({
          eventId: 88,
          payload: expect.objectContaining({
            fertilizer_form_id: 4,
            nutrients: [{ nutrient_id: 1, note: null }],
          }),
        })
      )
    )
    expect(createFertilizing.mutateAsync).not.toHaveBeenCalled()
  })

  it('drops prefilled nutrients when an organic event is switched to a non-organic form', async () => {
    renderWithProvider(<LogFertilizingForm plantId={2} onDone={vi.fn()} event={organicEvent} />)

    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Form' }), '1')
    await userEvent.click(screen.getByRole('button', { name: /Save changes/ }))

    await waitFor(() =>
      expect(updateEvent.mutateAsync).toHaveBeenCalledWith(
        expect.objectContaining({ payload: expect.objectContaining({ nutrients: [] }) })
      )
    )
  })
})
