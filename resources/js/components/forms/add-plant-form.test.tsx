import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { AddPlantForm } from './add-plant-form'
import { TooltipProvider } from '@/components/ui/tooltip'

vi.mock('@/hooks/useTags', () => ({
  useTags: () => ({ data: [], loading: false, error: null }),
  useCreateTag: () => ({ mutateAsync: vi.fn(), isPending: false }),
}))
vi.mock('@/hooks/useSensors', () => ({
  useSensors: () => ({ data: [], loading: false, error: null }),
}))
vi.mock('@/hooks/useSpeciesSuggest', () => ({
  useSpeciesSuggest: () => ({ results: [], loading: false }),
}))
vi.mock('@/hooks/useLocations', () => ({
  useLocations: () => ({ data: [], loading: false, error: null }),
  useCreateLocation: () => ({ mutateAsync: vi.fn(), isPending: false }),
}))
vi.mock('@/hooks/usePlantMutations', () => ({ useCreatePlant: vi.fn() }))
vi.mock('react-easy-crop', () => ({
  default: function MockCropper(props: Record<string, unknown>) {
    const callback = props.onCropComplete as ((...args: unknown[]) => void) | undefined
    const fire = () =>
      callback?.({ x: 0, y: 0, width: 100, height: 100 }, { x: 10, y: 15, width: 200, height: 300 })
    return (
      <div
        data-testid="cropper"
        data-aspect={props.aspect as number}
        role="button"
        tabIndex={0}
        onClick={fire}
        onKeyDown={fire}
      />
    )
  },
}))
vi.mock('@/components/app/app-context', () => ({
  useAppContext: () => ({ mobile: false }),
}))
vi.stubGlobal('URL', {
  ...globalThis.URL,
  createObjectURL: vi.fn(() => 'blob:mock-preview'),
  revokeObjectURL: vi.fn(),
})
import { useCreatePlant } from '@/hooks/usePlantMutations'

const mutateAsync = vi.fn().mockResolvedValue({ plant: { id: 1 }, coverUploadFailed: false })

const renderWithProvider = (ui: React.ReactElement) =>
  render(<TooltipProvider>{ui}</TooltipProvider>)

beforeEach(() => {
  mutateAsync.mockClear()
  vi.mocked(useCreatePlant).mockReturnValue({
    mutateAsync,
    isPending: false,
    isError: false,
  } as unknown as ReturnType<typeof useCreatePlant>)
})

describe('AddPlantForm', () => {
  it('creates a plant with the typed name and no cover file, then closes', async () => {
    const onDone = vi.fn()
    renderWithProvider(<AddPlantForm onDone={onDone} />)

    await userEvent.type(screen.getByPlaceholderText(/Pothos, Monstera/), 'Pothos')
    await userEvent.click(screen.getByRole('button', { name: /add plant/i }))

    expect(mutateAsync).toHaveBeenCalledWith(
      expect.objectContaining({
        payload: expect.objectContaining({
          common_name: 'Pothos',
          scientific_name: null,
          nickname: null,
          gbif_key: null,
          acquired_on: null,
          tag_ids: [],
        }),
        cover: null,
      })
    )
    await waitFor(() => expect(onDone).toHaveBeenCalled())
  })

  it('opens the crop workflow when a photo is chosen', async () => {
    renderWithProvider(<AddPlantForm onDone={vi.fn()} />)

    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    await userEvent.upload(input, new File(['img'], 'plant.jpg', { type: 'image/jpeg' }))

    expect(screen.getByText('Crop hero photo (2:3)')).toBeInTheDocument()
  })

  it('submits the cover with both crop areas after cropping', async () => {
    const onDone = vi.fn()
    renderWithProvider(<AddPlantForm onDone={onDone} />)

    await userEvent.type(screen.getByPlaceholderText(/Pothos, Monstera/), 'Pothos')
    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    const file = new File(['img'], 'plant.jpg', { type: 'image/jpeg' })
    await userEvent.upload(input, file)
    await userEvent.click(screen.getByTestId('cropper'))
    await userEvent.click(screen.getByRole('button', { name: /next/i }))
    await userEvent.click(screen.getByTestId('cropper'))
    await userEvent.click(screen.getByRole('button', { name: /save cover photo/i }))

    expect(screen.getByText('plant.jpg (cropped)')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /add plant/i }))

    expect(mutateAsync).toHaveBeenCalledWith(
      expect.objectContaining({
        cover: {
          file,
          heroCrop: { x: 10, y: 15, width: 200, height: 300 },
          thumbCrop: { x: 10, y: 15, width: 200, height: 300 },
        },
      })
    )
    await waitFor(() => expect(onDone).toHaveBeenCalled())
  })

  it('clears the pending photo when the crop is aborted', async () => {
    renderWithProvider(<AddPlantForm onDone={vi.fn()} />)

    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    await userEvent.upload(input, new File(['img'], 'plant.jpg', { type: 'image/jpeg' }))
    expect(screen.getByText('Crop hero photo (2:3)')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /back/i }))

    expect(screen.queryByText('Crop hero photo (2:3)')).not.toBeInTheDocument()
    expect(screen.getByText('Add a photo')).toBeInTheDocument()
  })

  it('sends nickname in the payload when provided', async () => {
    const onDone = vi.fn()
    renderWithProvider(<AddPlantForm onDone={onDone} />)

    await userEvent.type(screen.getByPlaceholderText(/Pothos, Monstera/), 'Pothos')
    await userEvent.type(screen.getByPlaceholderText(/Kitchen Pothos/), 'Kitchen Pothos')
    await userEvent.click(screen.getByRole('button', { name: /add plant/i }))

    expect(mutateAsync).toHaveBeenCalledWith(
      expect.objectContaining({
        payload: expect.objectContaining({
          common_name: 'Pothos',
          nickname: 'Kitchen Pothos',
        }),
      })
    )
    await waitFor(() => expect(onDone).toHaveBeenCalled())
  })
})
