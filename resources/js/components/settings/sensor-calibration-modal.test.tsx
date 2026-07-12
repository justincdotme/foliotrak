import type { ReactNode } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { delay, http, HttpResponse } from 'msw'
import type { Sensor } from '@/api/types'
import { server } from '@/test/handlers'
import calibrationFixture from '@/test/fixtures/sensors/calibration.json'
import { SensorCalibrationModal } from './sensor-calibration-modal'

const sensor: Sensor = {
  id: 9,
  mac: 'AC:A7:04:AA:00:05',
  device_name: 'Gondola-Moisture-01',
  hardware_type: 'gondola_moisture',
  type: 'moisture',
  name: 'Monstera probe',
  color: 'var(--series-3)',
  location: null,
  plant_count: 1,
  created_at: '2026-07-12T00:00:00.000000Z',
  updated_at: '2026-07-12T00:00:00.000000Z',
}

const renderModal = (onClose = vi.fn()) => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={qc}>{children}</QueryClientProvider>
  )
  render(<SensorCalibrationModal sensor={sensor} onClose={onClose} />, { wrapper })
  return onClose
}

describe('SensorCalibrationModal', () => {
  it('seeds anchors from saved points and autosaves an edited value on blur', async () => {
    let putBody: unknown = null
    server.use(
      http.put('/api/sensors/:id/calibration', async ({ request }) => {
        putBody = await request.json()
        return HttpResponse.json(calibrationFixture)
      })
    )

    renderModal()

    expect(await screen.findByText('Anchors: 1: 3100 · 5: 2200 · 10: 1300')).toBeInTheDocument()

    const input = screen.getByLabelText('Raw value at position 5')
    await userEvent.clear(input)
    await userEvent.type(input, '2400')
    await userEvent.tab()

    await waitFor(() =>
      expect(putBody).toEqual({
        points: [
          { position: 1, value: 3100 },
          { position: 5, value: 2400 },
          { position: 10, value: 1300 },
        ],
      })
    )
    expect(await screen.findByText('Saved')).toBeInTheDocument()
  })

  it('asks before closing with an unsaved edit and discards on request', async () => {
    let putCalls = 0
    server.use(
      http.put('/api/sensors/:id/calibration', () => {
        putCalls++
        return HttpResponse.json(calibrationFixture)
      })
    )

    const onClose = renderModal()

    const input = await screen.findByLabelText('Raw value at position 5')
    await userEvent.clear(input)
    await userEvent.type(input, '2400')
    await userEvent.keyboard('{Escape}')

    expect(await screen.findByText('Apply the unsaved calibration change?')).toBeInTheDocument()
    expect(onClose).not.toHaveBeenCalled()

    await userEvent.click(screen.getByRole('button', { name: 'Discard' }))
    expect(onClose).toHaveBeenCalled()
    expect(putCalls).toBe(0)
  })

  it('blocks further edits while a save is in flight', async () => {
    server.use(
      http.put('/api/sensors/:id/calibration', async () => {
        await delay(150)
        return HttpResponse.json(calibrationFixture)
      })
    )

    renderModal()

    const input = await screen.findByLabelText('Raw value at position 5')
    await userEvent.clear(input)
    await userEvent.type(input, '2400')
    await userEvent.tab()

    await waitFor(() => expect(input).toBeDisabled())
    await waitFor(() => expect(input).toBeEnabled())
    expect(await screen.findByText('Saved')).toBeInTheDocument()
  })

  it('seeds from suggested defaults when no anchors are saved', async () => {
    server.use(
      http.get('/api/sensors/:id/calibration', () =>
        HttpResponse.json({
          data: {
            points: [],
            suggested: [
              { position: 1, value: 3050 },
              { position: 5, value: 2175 },
              { position: 10, value: 1300 },
            ],
            latest: null,
          },
        })
      )
    )

    renderModal()

    expect(
      await screen.findByText('Defaults (full sensor range): 1: 3050 · 5: 2175 · 10: 1300')
    ).toBeInTheDocument()
  })

  it('explains the two-anchor requirement when nothing is saved or suggested', async () => {
    server.use(
      http.get('/api/sensors/:id/calibration', () =>
        HttpResponse.json({ data: { points: [], suggested: null, latest: null } })
      )
    )

    renderModal()

    expect(
      await screen.findByText(
        'No anchors yet. Save a raw value at two or more positions (dry end and wet end) to enable auto-fill.'
      )
    ).toBeInTheDocument()
  })

  it('removes the anchor at the current position via the remove button', async () => {
    let putBody: unknown = null
    server.use(
      http.put('/api/sensors/:id/calibration', async ({ request }) => {
        putBody = await request.json()
        return HttpResponse.json(calibrationFixture)
      })
    )

    renderModal()

    await screen.findByText('Anchors: 1: 3100 · 5: 2200 · 10: 1300')
    await userEvent.click(screen.getByRole('button', { name: 'Remove' }))

    await waitFor(() =>
      expect(putBody).toEqual({
        points: [
          { position: 1, value: 3100 },
          { position: 10, value: 1300 },
        ],
      })
    )
    expect(await screen.findByText('Saved')).toBeInTheDocument()
  })

  it('clearing a value and leaving the field removes that anchor', async () => {
    let putBody: unknown = null
    server.use(
      http.put('/api/sensors/:id/calibration', async ({ request }) => {
        putBody = await request.json()
        return HttpResponse.json(calibrationFixture)
      })
    )

    renderModal()

    const input = await screen.findByLabelText('Raw value at position 5')
    await userEvent.clear(input)
    await userEvent.tab()

    await waitFor(() =>
      expect(putBody).toEqual({
        points: [
          { position: 1, value: 3100 },
          { position: 10, value: 1300 },
        ],
      })
    )
  })

  it('dismisses the close confirmation when editing resumes', async () => {
    renderModal()

    const input = await screen.findByLabelText('Raw value at position 5')
    await userEvent.clear(input)
    await userEvent.type(input, '2400')
    await userEvent.keyboard('{Escape}')

    expect(await screen.findByText('Apply the unsaved calibration change?')).toBeInTheDocument()

    await userEvent.type(input, '5')

    expect(screen.queryByText('Apply the unsaved calibration change?')).not.toBeInTheDocument()
  })
})
