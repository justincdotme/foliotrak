import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { AddPlantForm } from './add-plant-form'

vi.mock('@/hooks/useTags', () => ({
  useTags: () => ({ data: [], loading: false, error: null }),
}))
vi.mock('@/hooks/useSpeciesSuggest', () => ({
  useSpeciesSuggest: () => ({ results: [], loading: false }),
}))
vi.mock('@/hooks/useLocations', () => ({
  useLocations: () => ({ data: [], loading: false, error: null }),
  useCreateLocation: () => ({ mutateAsync: vi.fn(), isPending: false }),
}))
vi.mock('@/hooks/usePlantMutations', () => ({ useCreatePlant: vi.fn() }))
import { useCreatePlant } from '@/hooks/usePlantMutations'

const mutateAsync = vi.fn().mockResolvedValue({ id: 1 })

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
    render(<AddPlantForm onDone={onDone} />)

    await userEvent.type(screen.getByPlaceholderText(/Pothos, Monstera/), 'Pothos')
    await userEvent.click(screen.getByRole('button', { name: /add plant/i }))

    expect(mutateAsync).toHaveBeenCalledWith(
      expect.objectContaining({
        payload: expect.objectContaining({
          common_name: 'Pothos',
          scientific_name: null,
          gbif_key: null,
          tag_ids: [],
        }),
        coverFile: null,
      })
    )
    await waitFor(() => expect(onDone).toHaveBeenCalled())
  })
})
