import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { SettingsPage } from './settings'
import type { User } from '@/api/types'

vi.mock('@/hooks/useSettings', () => ({
  useSettings: vi.fn(),
  useUpdateSettings: vi.fn(),
}))
vi.mock('@/hooks/useCurrentUser', () => ({ useCurrentUser: vi.fn() }))
vi.mock('@/hooks/useTags', () => ({
  useTags: vi.fn(),
  useCreateTag: vi.fn(),
  useUpdateTag: vi.fn(),
  useDeleteTag: vi.fn(),
}))
vi.mock('@/hooks/useEquipment', () => ({
  useEquipment: vi.fn(),
  useCreateEquipment: vi.fn(),
  useUpdateEquipment: vi.fn(),
  useDeleteEquipment: vi.fn(),
}))
vi.mock('@/hooks/useSensors', () => ({
  useSensors: vi.fn(),
  useDiscoverSensors: vi.fn(),
  useTestConnection: vi.fn(),
  useCreateSensor: vi.fn(),
  useUpdateSensor: vi.fn(),
  useDeleteSensor: vi.fn(),
}))

import { useSettings, useUpdateSettings } from '@/hooks/useSettings'
import { useCurrentUser } from '@/hooks/useCurrentUser'
import { useTags, useCreateTag, useUpdateTag, useDeleteTag } from '@/hooks/useTags'
import {
  useEquipment,
  useCreateEquipment,
  useUpdateEquipment,
  useDeleteEquipment,
} from '@/hooks/useEquipment'
import {
  useSensors,
  useDiscoverSensors,
  useTestConnection,
  useCreateSensor,
  useUpdateSensor,
  useDeleteSensor,
} from '@/hooks/useSensors'

const SAVED_KEY = 'z9y8x7w6v5'.repeat(3)
const NEW_KEY = 'a1b2c3d4e5'.repeat(3)

const USER: User = {
  id: 1,
  name: 'Justin Tester',
  email: 'j@home.lan',
  pushover_user_key: SAVED_KEY,
}

const TAGS = [
  { id: 1, name: 'Tropical', color: 'var(--series-1)' },
  { id: 2, name: 'Succulent', color: 'var(--series-2)' },
]

const EQUIPMENT = [
  { id: 1, key: 'grow_light', label: 'Grow Light', sort_order: 1 },
  { id: 2, key: 'humidifier', label: 'Humidifier', sort_order: 2 },
]

function mockTagHooks() {
  vi.mocked(useTags).mockReturnValue({ data: TAGS, loading: false, error: null })
  vi.mocked(useCreateTag).mockReturnValue({ mutateAsync: vi.fn(), isPending: false } as never)
  vi.mocked(useUpdateTag).mockReturnValue({ mutateAsync: vi.fn() } as never)
  vi.mocked(useDeleteTag).mockReturnValue({ mutate: vi.fn() } as never)
}

function mockEquipmentHooks() {
  vi.mocked(useEquipment).mockReturnValue({ data: EQUIPMENT, loading: false })
  vi.mocked(useCreateEquipment).mockReturnValue({
    mutateAsync: vi.fn(),
    isPending: false,
  } as never)
  vi.mocked(useUpdateEquipment).mockReturnValue({ mutateAsync: vi.fn() } as never)
  vi.mocked(useDeleteEquipment).mockReturnValue({ mutate: vi.fn() } as never)
}

function mockSensorHooks() {
  vi.mocked(useSensors).mockReturnValue({ data: [], loading: false, error: null })
  vi.mocked(useDiscoverSensors).mockReturnValue({
    data: undefined,
    isSuccess: false,
    isFetching: false,
    refetch: vi.fn(),
  } as never)
  vi.mocked(useTestConnection).mockReturnValue({
    mutate: vi.fn(),
    isPending: false,
    data: undefined,
  } as never)
  vi.mocked(useCreateSensor).mockReturnValue({ mutateAsync: vi.fn(), isPending: false } as never)
  vi.mocked(useUpdateSensor).mockReturnValue({ mutateAsync: vi.fn() } as never)
  vi.mocked(useDeleteSensor).mockReturnValue({ mutate: vi.fn() } as never)
}

function setup(opts: {
  key?: string | null
  loading?: boolean
  error?: Error | null
  mutateAsync?: ReturnType<typeof vi.fn>
}) {
  const mutateAsync = opts.mutateAsync ?? vi.fn().mockResolvedValue({ pushover_user_key: null })
  vi.mocked(useSettings).mockReturnValue({
    data:
      opts.loading || opts.error
        ? null
        : { pushover_user_key: opts.key ?? null, temperature_unit: 'F' as const },
    loading: opts.loading ?? false,
    error: opts.error ?? null,
  })
  vi.mocked(useUpdateSettings).mockReturnValue({ mutateAsync } as never)
  vi.mocked(useCurrentUser).mockReturnValue({ user: USER, loading: false, error: null })
  const view = render(<SettingsPage theme="system" setTheme={vi.fn()} onLogout={vi.fn()} />)
  return { mutateAsync, rerender: view.rerender }
}

function settingsValue(key: string | null) {
  vi.mocked(useSettings).mockReturnValue({
    data: { pushover_user_key: key, temperature_unit: 'F' as const },
    loading: false,
    error: null,
  })
}

beforeEach(() => {
  vi.clearAllMocks()
  mockTagHooks()
  mockEquipmentHooks()
  mockSensorHooks()
})

describe('SettingsPage', () => {
  it('shows the saved key and the live account details', () => {
    setup({ key: SAVED_KEY })

    expect((screen.getByRole('textbox') as HTMLInputElement).value).toBe(SAVED_KEY)
    expect(screen.getByText('Justin Tester')).toBeInTheDocument()
    expect(screen.getByText('j@home.lan')).toBeInTheDocument()
  })

  it('saves a valid 30-character key', async () => {
    const { mutateAsync } = setup({ key: null })

    await userEvent.type(screen.getByRole('textbox'), NEW_KEY)
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(mutateAsync).toHaveBeenCalledWith({ pushover_user_key: NEW_KEY })
    expect(await screen.findByText('Saved')).toBeInTheDocument()
  })

  it('keeps the saved confirmation after the settings cache updates', async () => {
    const mutateAsync = vi.fn().mockResolvedValue({ pushover_user_key: NEW_KEY })
    const { rerender } = setup({ key: null, mutateAsync })

    await userEvent.type(screen.getByRole('textbox'), NEW_KEY)
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))
    expect(await screen.findByText('Saved')).toBeInTheDocument()

    // A successful save seeds ['settings'] with the saved value, which re-renders
    // the page with the new key. The confirmation must survive that re-render.
    settingsValue(NEW_KEY)
    rerender(<SettingsPage theme="system" setTheme={vi.fn()} onLogout={vi.fn()} />)

    expect(screen.getByText('Saved')).toBeInTheDocument()
    expect((screen.getByRole('textbox') as HTMLInputElement).value).toBe(NEW_KEY)
  })

  it('sends null to clear the key when the field is emptied', async () => {
    const { mutateAsync } = setup({ key: SAVED_KEY })

    await userEvent.clear(screen.getByRole('textbox'))
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(mutateAsync).toHaveBeenCalledWith({ pushover_user_key: null })
  })

  // Each case isolates one half of the rule the client mirrors from the server
  // (exactly 30 chars, alphanumeric only), so a regression in either half is caught.
  it.each([
    ['too short', 'abc'],
    ['one character too long', `${'a1b2c3d4e5'.repeat(3)}x`],
    ['not alphanumeric', `${'a1b2c3d4e5'.repeat(2)}a1b2c3d4e!`],
  ])('blocks an invalid key (%s) and does not call the API', async (_label, value) => {
    const { mutateAsync } = setup({ key: null })

    await userEvent.type(screen.getByRole('textbox'), value)
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText(/30-character/i)).toBeInTheDocument()
    expect(mutateAsync).not.toHaveBeenCalled()
  })

  it('surfaces a server validation error', async () => {
    const mutateAsync = vi.fn().mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: { errors: { pushover_user_key: ['That key was rejected by Pushover.'] } },
      },
    })
    setup({ key: null, mutateAsync })

    await userEvent.type(screen.getByRole('textbox'), NEW_KEY)
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('That key was rejected by Pushover.')).toBeInTheDocument()
  })

  it('renders an error state when settings fail to load', () => {
    setup({ error: new Error('boom') })

    expect(screen.getByText(/unable to load/i)).toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
  })

  it('shows neither the form nor an error while settings load', () => {
    setup({ loading: true })

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.queryByText(/unable to load/i)).not.toBeInTheDocument()
  })
})

describe('TagManager', () => {
  it('lists existing tags with rename and delete controls', () => {
    setup({ key: null })

    expect(screen.getByText('Tropical')).toBeInTheDocument()
    expect(screen.getByText('Succulent')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /rename tropical/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /delete tropical/i })).toBeInTheDocument()
  })

  it('shows empty state when no tags exist', () => {
    vi.mocked(useTags).mockReturnValue({ data: [], loading: false, error: null })
    setup({ key: null })

    expect(screen.getByText(/no tags yet/i)).toBeInTheDocument()
  })

  it('opens the add-tag input when clicking the add button', async () => {
    setup({ key: null })

    await userEvent.click(screen.getByText(/add a tag/i))
    expect(screen.getByPlaceholderText(/tag name/i)).toBeInTheDocument()
  })

  it('creates a tag via the add input', async () => {
    const mutateAsync = vi
      .fn()
      .mockResolvedValue({ id: 3, name: 'Trailing', color: 'var(--series-3)' })
    vi.mocked(useCreateTag).mockReturnValue({ mutateAsync, isPending: false } as never)
    setup({ key: null })

    await userEvent.click(screen.getByText(/add a tag/i))
    await userEvent.type(screen.getByPlaceholderText(/tag name/i), 'Trailing')
    await userEvent.click(screen.getByRole('button', { name: 'Add' }))

    expect(mutateAsync).toHaveBeenCalledWith('Trailing')
  })

  it('enters rename mode and calls update', async () => {
    const mutateAsync = vi.fn().mockResolvedValue({ id: 1, name: 'Fern', color: 'var(--series-1)' })
    vi.mocked(useUpdateTag).mockReturnValue({ mutateAsync } as never)
    setup({ key: null })

    await userEvent.click(screen.getByRole('button', { name: /rename tropical/i }))

    const inputs = screen.getAllByRole('textbox')
    const renameInput = inputs.find(
      el => (el as HTMLInputElement).value === 'Tropical'
    ) as HTMLInputElement
    expect(renameInput).toBeTruthy()

    await userEvent.clear(renameInput)
    await userEvent.type(renameInput, 'Fern')
    await userEvent.keyboard('{Enter}')

    expect(mutateAsync).toHaveBeenCalledWith({ id: 1, payload: { name: 'Fern' } })
  })

  it('shows a delete confirmation before removing a tag', async () => {
    setup({ key: null })

    await userEvent.click(screen.getByRole('button', { name: /delete tropical/i }))

    expect(screen.getByText(/will be removed from all plants/i)).toBeInTheDocument()
  })
})

describe('EquipmentManager', () => {
  it('lists existing equipment with rename and delete controls', () => {
    setup({ key: null })

    expect(screen.getByText('Grow Light')).toBeInTheDocument()
    expect(screen.getByText('Humidifier')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /rename grow light/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /delete grow light/i })).toBeInTheDocument()
  })

  it('shows empty state when no equipment exists', () => {
    vi.mocked(useEquipment).mockReturnValue({ data: [], loading: false })
    setup({ key: null })

    expect(screen.getByText(/no equipment yet/i)).toBeInTheDocument()
  })

  it('enters rename mode and calls update', async () => {
    const mutateAsync = vi
      .fn()
      .mockResolvedValue({ id: 1, key: 'led_panel', label: 'LED Panel', sort_order: 1 })
    vi.mocked(useUpdateEquipment).mockReturnValue({ mutateAsync } as never)
    setup({ key: null })

    await userEvent.click(screen.getByRole('button', { name: /rename grow light/i }))

    const inputs = screen.getAllByRole('textbox')
    const renameInput = inputs.find(
      el => (el as HTMLInputElement).value === 'Grow Light'
    ) as HTMLInputElement
    expect(renameInput).toBeTruthy()

    await userEvent.clear(renameInput)
    await userEvent.type(renameInput, 'LED Panel')
    await userEvent.keyboard('{Enter}')

    expect(mutateAsync).toHaveBeenCalledWith({ id: 1, payload: { label: 'LED Panel' } })
  })

  it('shows a delete confirmation before removing equipment', async () => {
    setup({ key: null })

    await userEvent.click(screen.getByRole('button', { name: /delete humidifier/i }))

    expect(screen.getByText(/will be removed from all plants/i)).toBeInTheDocument()
  })

  it('creates equipment via the add input', async () => {
    const mutateAsync = vi
      .fn()
      .mockResolvedValue({ id: 3, key: 'heat_mat', label: 'Heat Mat', sort_order: 3 })
    vi.mocked(useCreateEquipment).mockReturnValue({ mutateAsync, isPending: false } as never)
    setup({ key: null })

    await userEvent.click(screen.getByText(/add equipment/i))
    await userEvent.type(screen.getByPlaceholderText(/equipment name/i), 'Heat Mat')
    await userEvent.click(screen.getByRole('button', { name: 'Add' }))

    expect(mutateAsync).toHaveBeenCalledWith('Heat Mat')
  })
})
