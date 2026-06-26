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

import { useSettings, useUpdateSettings } from '@/hooks/useSettings'
import { useCurrentUser } from '@/hooks/useCurrentUser'

const SAVED_KEY = 'z9y8x7w6v5'.repeat(3)
const NEW_KEY = 'a1b2c3d4e5'.repeat(3)

const USER: User = {
  id: 1,
  name: 'Justin Tester',
  email: 'j@home.lan',
  pushover_user_key: SAVED_KEY,
}

function setup(opts: {
  key?: string | null
  loading?: boolean
  error?: Error | null
  mutateAsync?: ReturnType<typeof vi.fn>
}) {
  const mutateAsync = opts.mutateAsync ?? vi.fn().mockResolvedValue({ pushover_user_key: null })
  vi.mocked(useSettings).mockReturnValue({
    data: opts.loading || opts.error ? null : { pushover_user_key: opts.key ?? null },
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
    data: { pushover_user_key: key },
    loading: false,
    error: null,
  })
}

beforeEach(() => vi.clearAllMocks())

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
