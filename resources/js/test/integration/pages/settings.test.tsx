import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import type { ReactNode } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { SettingsPage } from '@/pages/settings'
import { TooltipProvider } from '@/components/ui/tooltip'
import { server } from '../../handlers'
import userFixture from '../../fixtures/user.json'
import tagsFixture from '../../fixtures/lookups/tags.json'
import tagCreatedFixture from '../../fixtures/tags/created-201.json'
import settingsUpdatedFixture from '../../fixtures/settings/updated.json'

const makeWrapper = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <TooltipProvider>
        <QueryClientProvider client={qc}>{children}</QueryClientProvider>
      </TooltipProvider>
    )
  }
}

const renderPage = () =>
  render(<SettingsPage theme="light" setTheme={vi.fn()} onLogout={vi.fn()} />, {
    wrapper: makeWrapper(),
  })

const existingTagName = tagsFixture.data[0]?.name ?? ''
const NEW_KEY = 'a1b2c3d4e5'.repeat(3)

describe('SettingsPage', () => {
  it('renders real settings and account data from the default fixtures', async () => {
    renderPage()

    const keyField = await screen.findByRole('textbox')
    expect((keyField as HTMLInputElement).value).toBe('')
    expect(screen.getByText(userFixture.name)).toBeInTheDocument()
    expect(screen.getByText(userFixture.email)).toBeInTheDocument()
    expect(await screen.findByText(existingTagName)).toBeInTheDocument()
  })

  it('saves a new Pushover key through the real PATCH handler', async () => {
    const requests: Array<{ body: unknown }> = []
    server.use(
      http.patch('/api/settings', async ({ request }) => {
        requests.push({ body: await request.json() })
        return HttpResponse.json(settingsUpdatedFixture, { status: 200 })
      })
    )
    renderPage()

    const keyField = await screen.findByRole('textbox')
    await userEvent.type(keyField, NEW_KEY)
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('Saved')).toBeInTheDocument()
    expect(requests[0]).toMatchObject({ body: { pushover_user_key: NEW_KEY } })
  })

  it('creates a tag through the real POST handler and shows it in the list', async () => {
    const requests: Array<{ body: unknown }> = []
    server.use(
      http.post('/api/tags', async ({ request }) => {
        requests.push({ body: await request.json() })
        return HttpResponse.json(tagCreatedFixture, { status: 201 })
      })
    )
    renderPage()

    await screen.findByText(existingTagName)
    await userEvent.click(screen.getByText(/add a tag/i))
    await userEvent.type(screen.getByPlaceholderText(/tag name/i), 'Trailing2')
    await userEvent.click(screen.getByRole('button', { name: 'Add' }))

    expect(await screen.findByText(tagCreatedFixture.data.name)).toBeInTheDocument()
    expect(requests[0]).toMatchObject({ body: { name: 'Trailing2' } })
  })
})
