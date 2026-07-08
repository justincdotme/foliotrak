import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { CareEvent } from '@/api/types'
import { LogObservationForm } from './log-observation-form'
import { TooltipProvider } from '@/components/ui/tooltip'

const renderWithProvider = (ui: React.ReactElement) =>
  render(<TooltipProvider>{ui}</TooltipProvider>)

vi.mock('@/hooks/useCareLookups', () => ({
  useSymptoms: () => ({
    data: [
      {
        id: 1,
        category: 'leaf',
        key: 'yellow_leaf',
        label: 'Yellowing leaves',
        sort_order: 1,
        is_custom: false,
      },
      {
        id: 10,
        category: 'pest',
        key: 'spider_mites',
        label: 'Spider mites',
        sort_order: 10,
        is_custom: false,
      },
    ],
    loading: false,
    error: null,
  }),
}))

vi.mock('@/hooks/useSettings', () => ({
  useSettings: () => ({
    data: { pushover_user_key: null, temperature_unit: 'F' as const },
    loading: false,
    error: null,
  }),
}))

vi.mock('@/hooks/useCareEventMutations', () => ({ useCareEventMutations: vi.fn() }))
import { useCareEventMutations } from '@/hooks/useCareEventMutations'

const createObservation = { mutateAsync: vi.fn() }
const updateEvent = { mutateAsync: vi.fn() }
const uploadEventPhoto = { mutateAsync: vi.fn() }

beforeEach(() => {
  vi.clearAllMocks()
  createObservation.mutateAsync.mockResolvedValue({ id: 555 })
  updateEvent.mutateAsync.mockResolvedValue({ id: 555 })
  uploadEventPhoto.mutateAsync.mockResolvedValue({ id: 9 })
  vi.mocked(useCareEventMutations).mockReturnValue({
    createWatering: { mutateAsync: vi.fn() },
    createFertilizing: { mutateAsync: vi.fn() },
    createRepotting: { mutateAsync: vi.fn() },
    createObservation,
    updateEvent,
    deleteEvent: { mutateAsync: vi.fn() },
    uploadEventPhoto,
  } as unknown as ReturnType<typeof useCareEventMutations>)
})

describe('LogObservationForm', () => {
  it('sends weight as lb/oz/g, splits seeded and custom symptoms, and links an attached photo', async () => {
    const onDone = vi.fn()
    const { container } = renderWithProvider(<LogObservationForm plantId={3} onDone={onDone} />)

    await userEvent.click(screen.getByRole('button', { name: /Yellowing leaves/ }))
    await userEvent.type(screen.getByLabelText('Custom symptom'), 'curled tips')
    await userEvent.click(screen.getByRole('button', { name: /^Add$/ }))

    await userEvent.clear(screen.getByLabelText('Grams'))
    await userEvent.type(screen.getByLabelText('Grams'), '120')

    const file = new File(['x'], 'leaf.jpg', { type: 'image/jpeg' })
    await userEvent.upload(container.querySelector('input[type="file"]') as HTMLInputElement, file)

    await userEvent.click(screen.getByRole('button', { name: /Log observation/ }))

    await waitFor(() =>
      expect(createObservation.mutateAsync).toHaveBeenCalledWith(
        expect.objectContaining({
          weight: { lb: 0, oz: 0, g: 120 },
          symptom_ids: [1],
          custom_symptoms: ['curled tips'],
        })
      )
    )

    await waitFor(() =>
      expect(uploadEventPhoto.mutateAsync).toHaveBeenCalledWith({ file, careEventId: 555 })
    )
    expect(onDone).toHaveBeenCalled()
  })

  it('does not create a duplicate observation when resubmitting after a failed photo upload', async () => {
    const onDone = vi.fn()
    uploadEventPhoto.mutateAsync
      .mockRejectedValueOnce(new Error('Photo too large'))
      .mockResolvedValueOnce({ id: 9 })
    const { container } = renderWithProvider(<LogObservationForm plantId={3} onDone={onDone} />)

    const file = new File(['x'], 'leaf.jpg', { type: 'image/jpeg' })
    await userEvent.upload(container.querySelector('input[type="file"]') as HTMLInputElement, file)
    await userEvent.click(screen.getByRole('button', { name: /Log observation/ }))

    expect(await screen.findByText('Photo too large')).toBeInTheDocument()
    expect(onDone).not.toHaveBeenCalled()

    await userEvent.click(screen.getByRole('button', { name: /Log observation/ }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(createObservation.mutateAsync).toHaveBeenCalledTimes(1)
    expect(uploadEventPhoto.mutateAsync).toHaveBeenCalledTimes(2)
    expect(uploadEventPhoto.mutateAsync).toHaveBeenNthCalledWith(2, { file, careEventId: 555 })
  })

  it('still resubmits the update on retry after a failed photo upload when editing', async () => {
    const event: CareEvent = {
      id: 77,
      plant_id: 3,
      care_event_type_id: 4,
      type: 'observation',
      occurred_at: '2026-06-18T18:00:00.000Z',
      logged_by_user_id: 1,
      note: 'weekly check',
      created_at: '2026-06-18T18:00:00.000Z',
      updated_at: '2026-06-18T18:00:00.000Z',
      observation: {
        care_event_id: 77,
        overall_health: 4,
        health_note: null,
        light_level: 6,
        growth_rate: 'moderate',
        growth_note: null,
        leaf_size_mm: 90,
        weight_grams: 1200,
        weight: { lb: 2, oz: 10, g: 4.6 },
        ambient_humidity_pct: null,
        ambient_temp_c: null,
        ambient_temp_display: null,
        temperature_unit: 'F' as const,
        soil_moisture_relative: null,
        soil_moisture_precise: null,
        symptoms: [],
      },
    }
    const onDone = vi.fn()
    uploadEventPhoto.mutateAsync
      .mockRejectedValueOnce(new Error('Photo too large'))
      .mockResolvedValueOnce({ id: 9 })

    const { container } = renderWithProvider(
      <LogObservationForm plantId={3} onDone={onDone} event={event} />
    )

    const file = new File(['x'], 'leaf.jpg', { type: 'image/jpeg' })
    await userEvent.upload(container.querySelector('input[type="file"]') as HTMLInputElement, file)
    await userEvent.click(screen.getByRole('button', { name: /Save changes/ }))

    expect(await screen.findByText('Photo too large')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Save changes/ }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(updateEvent.mutateAsync).toHaveBeenCalledTimes(2)
    expect(createObservation.mutateAsync).not.toHaveBeenCalled()
  })

  it('omits weight when no components are entered', async () => {
    renderWithProvider(<LogObservationForm plantId={3} onDone={vi.fn()} />)

    await userEvent.click(screen.getByRole('button', { name: /Log observation/ }))

    await waitFor(() =>
      expect(createObservation.mutateAsync).toHaveBeenCalledWith(
        expect.objectContaining({ weight: null })
      )
    )
  })

  it('prefills from an event and routes an edit to update, splitting seeded and custom symptoms', async () => {
    const event: CareEvent = {
      id: 77,
      plant_id: 3,
      care_event_type_id: 4,
      type: 'observation',
      occurred_at: '2026-06-18T18:00:00.000Z',
      logged_by_user_id: 1,
      note: 'weekly check',
      created_at: '2026-06-18T18:00:00.000Z',
      updated_at: '2026-06-18T18:00:00.000Z',
      observation: {
        care_event_id: 77,
        overall_health: 4,
        health_note: null,
        light_level: 6,
        growth_rate: 'moderate',
        growth_note: null,
        leaf_size_mm: 90,
        weight_grams: 1200,
        weight: { lb: 2, oz: 10, g: 4.6 },
        ambient_humidity_pct: null,
        ambient_temp_c: null,
        ambient_temp_display: null,
        temperature_unit: 'F' as const,
        soil_moisture_relative: null,
        soil_moisture_precise: null,
        symptoms: [
          {
            id: 1,
            category: 'leaf',
            key: 'yellow_leaf',
            label: 'Yellowing leaves',
            sort_order: 1,
            is_custom: false,
          },
          {
            id: 99,
            category: 'custom',
            key: 'curled_tips',
            label: 'curled tips',
            sort_order: 99,
            is_custom: true,
          },
        ],
      },
    }

    renderWithProvider(<LogObservationForm plantId={3} onDone={vi.fn()} event={event} />)

    expect(screen.getByLabelText('Pounds')).toHaveValue(2)
    expect(screen.getByLabelText('Ounces')).toHaveValue(10)
    expect(screen.getByLabelText('Grams')).toHaveValue(4.6)
    // The custom symptom is restored as an editable chip, not a lookup chip.
    expect(screen.getByText('curled tips')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Save changes/ }))

    await waitFor(() =>
      expect(updateEvent.mutateAsync).toHaveBeenCalledWith(
        expect.objectContaining({
          eventId: 77,
          payload: expect.objectContaining({
            weight: { lb: 2, oz: 10, g: 4.6 },
            symptom_ids: [1],
            custom_symptoms: ['curled tips'],
          }),
        })
      )
    )
    expect(createObservation.mutateAsync).not.toHaveBeenCalled()
  })
})
