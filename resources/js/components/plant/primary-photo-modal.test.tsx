import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { PrimaryPhotoModal } from './primary-photo-modal'

vi.mock('@/components/app/app-context', () => ({
  useAppContext: () => ({ mobile: false }),
}))

vi.mock('@/hooks/usePlantMutations', () => ({
  useUploadPhoto: vi.fn(),
  useSetCoverPhoto: vi.fn(),
  useDeletePhoto: vi.fn(),
}))

vi.mock('./crop-workflow', () => ({
  CropWorkflow: function MockCropWorkflow(props: Record<string, unknown>) {
    const onComplete = props.onComplete as (a: object, b: object) => void
    const onBack = props.onBack as () => void
    return (
      <div data-testid="crop-workflow">
        <button
          onClick={() =>
            onComplete(
              { x: 10, y: 15, width: 200, height: 300 },
              { x: 5, y: 5, width: 100, height: 100 }
            )
          }
        >
          Complete crop
        </button>
        <button onClick={onBack}>Back to pick</button>
      </div>
    )
  },
}))

vi.stubGlobal('URL', {
  ...globalThis.URL,
  createObjectURL: vi.fn(() => 'blob:mock-preview'),
  revokeObjectURL: vi.fn(),
})

import { useUploadPhoto, useSetCoverPhoto, useDeletePhoto } from '@/hooks/usePlantMutations'

const uploadMutateAsync = vi.fn().mockResolvedValue({ id: 1 })
const setCoverMutateAsync = vi.fn().mockResolvedValue({})
const deleteMutateAsync = vi.fn().mockResolvedValue(undefined)

beforeEach(() => {
  uploadMutateAsync.mockClear()
  setCoverMutateAsync.mockClear()
  deleteMutateAsync.mockClear()
  vi.mocked(useUploadPhoto).mockReturnValue({
    mutateAsync: uploadMutateAsync,
    isPending: false,
    isError: false,
  } as ReturnType<typeof useUploadPhoto>)
  vi.mocked(useSetCoverPhoto).mockReturnValue({
    mutateAsync: setCoverMutateAsync,
    isPending: false,
    isError: false,
  } as ReturnType<typeof useSetCoverPhoto>)
  vi.mocked(useDeletePhoto).mockReturnValue({
    mutateAsync: deleteMutateAsync,
    isPending: false,
    isError: false,
  } as ReturnType<typeof useDeletePhoto>)
})

const plant = {
  id: 1,
  common_name: 'Pothos',
  cover_photo_id: null,
} as Record<string, unknown>

describe('PrimaryPhotoModal', () => {
  it('enters the crop workflow when a photo is uploaded', async () => {
    render(<PrimaryPhotoModal plant={plant} photos={[]} open onClose={vi.fn()} />)
    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    await userEvent.upload(input, new File(['pixels'], 'photo.jpg', { type: 'image/jpeg' }))
    expect(screen.getByTestId('crop-workflow')).toBeInTheDocument()
  })

  it('submits both hero and thumbnail crop coordinates with the upload', async () => {
    const onClose = vi.fn()
    render(<PrimaryPhotoModal plant={plant} photos={[]} open onClose={onClose} />)
    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    await userEvent.upload(input, new File(['pixels'], 'photo.jpg', { type: 'image/jpeg' }))
    await userEvent.click(screen.getByText('Complete crop'))
    await waitFor(() => {
      expect(uploadMutateAsync).toHaveBeenCalledWith(
        expect.objectContaining({
          heroCrop: { x: 10, y: 15, width: 200, height: 300 },
          thumbCrop: { x: 5, y: 5, width: 100, height: 100 },
          setAsCover: true,
        })
      )
    })
  })

  it('returns to the pick step when Back is pressed in the crop workflow', async () => {
    render(<PrimaryPhotoModal plant={plant} photos={[]} open onClose={vi.fn()} />)
    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    await userEvent.upload(input, new File(['pixels'], 'photo.jpg', { type: 'image/jpeg' }))
    expect(screen.getByTestId('crop-workflow')).toBeInTheDocument()
    await userEvent.click(screen.getByText('Back to pick'))
    expect(screen.queryByTestId('crop-workflow')).not.toBeInTheDocument()
  })
})
