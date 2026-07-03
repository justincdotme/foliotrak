import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import type { ReactNode } from 'react'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { AddPlantForm } from '@/components/forms/add-plant-form'
import { server } from '../../../handlers'
import { jsonMessage } from '../../../handlers/_helpers'

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

// Assigned onto the real URL class (not vi.stubGlobal) so MSW can keep
// constructing URL instances while jsdom gains the missing object-URL methods.
beforeAll(() => {
  globalThis.URL.createObjectURL = vi.fn(() => 'blob:mock-preview') as typeof URL.createObjectURL
  globalThis.URL.revokeObjectURL = vi.fn() as typeof URL.revokeObjectURL
})

function makeWrapper() {
  const qc = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={qc}>{children}</QueryClientProvider>
  }
}

describe('AddPlantForm', () => {
  it('sends the typed name in the real request body and calls onDone on success', async () => {
    const onDone = vi.fn()
    const requestBodies: unknown[] = []
    server.use(
      http.post('/api/plants', async ({ request }) => {
        requestBodies.push(await request.json())
        return HttpResponse.json({ data: { id: 99, common_name: 'Pothos' } }, { status: 201 })
      })
    )
    render(<AddPlantForm onDone={onDone} />, { wrapper: makeWrapper() })

    await userEvent.type(screen.getByPlaceholderText(/Pothos, Monstera/), 'Pothos')
    await userEvent.click(screen.getByRole('button', { name: /add plant/i }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(requestBodies[0]).toMatchObject({
      common_name: 'Pothos',
      scientific_name: null,
      nickname: null,
      gbif_key: null,
      location_id: null,
      acquired_on: null,
      tag_ids: [],
    })
  })

  it('routes the cover photo through the crop workflow and uploads both crops', async () => {
    const onDone = vi.fn()
    const uploadForms: FormData[] = []
    server.use(
      http.post('/api/plants', () =>
        HttpResponse.json({ data: { id: 99, common_name: 'Pothos' } }, { status: 201 })
      ),
      http.post('/api/plants/:id/photos', async ({ request }) => {
        uploadForms.push(await request.formData())
        return HttpResponse.json({ data: { id: 7, plant_id: 99 } }, { status: 201 })
      })
    )
    render(<AddPlantForm onDone={onDone} />, { wrapper: makeWrapper() })

    await userEvent.type(screen.getByPlaceholderText(/Pothos, Monstera/), 'Pothos')
    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    await userEvent.upload(input, new File(['img'], 'plant.jpg', { type: 'image/jpeg' }))
    await userEvent.click(screen.getByTestId('cropper'))
    await userEvent.click(screen.getByRole('button', { name: /next/i }))
    await userEvent.click(screen.getByTestId('cropper'))
    await userEvent.click(screen.getByRole('button', { name: /save cover photo/i }))
    await userEvent.click(screen.getByRole('button', { name: /add plant/i }))

    await waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(uploadForms[0]?.get('set_as_cover')).toBe('1')
    expect(JSON.parse(uploadForms[0]?.get('hero_crop') as string)).toEqual({
      x: 10,
      y: 15,
      width: 200,
      height: 300,
    })
    expect(JSON.parse(uploadForms[0]?.get('thumb_crop') as string)).toEqual({
      x: 10,
      y: 15,
      width: 200,
      height: 300,
    })
  })

  it('shows the real server error and does not call onDone when creation fails', async () => {
    const onDone = vi.fn()
    server.use(http.post('/api/plants', () => jsonMessage(500, 'Could not save plant.')))
    render(<AddPlantForm onDone={onDone} />, { wrapper: makeWrapper() })

    await userEvent.type(screen.getByPlaceholderText(/Pothos, Monstera/), 'Pothos')
    await userEvent.click(screen.getByRole('button', { name: /add plant/i }))

    expect(await screen.findByText('Could not save plant.')).toBeInTheDocument()
    expect(onDone).not.toHaveBeenCalled()
  })
})
