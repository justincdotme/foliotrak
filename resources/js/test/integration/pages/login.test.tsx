import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider, useQuery } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import { MemoryRouter, Routes, Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AppRoot } from '@/app-root'
import { getUser } from '@/api/client'
import { LoginPage } from '@/pages/login'
import { AuthGate } from '@/components/shell/auth-gate'
import { TooltipProvider } from '@/components/ui/tooltip'
import { server } from '../../handlers'
import { jsonMessage } from '../../handlers/_helpers'
import loginSuccess from '../../fixtures/auth/login-200.json'

const renderLoginRoutes = () =>
  render(
    <QueryClientProvider
      client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
    >
      <TooltipProvider>
        <MemoryRouter initialEntries={['/login']}>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/" element={<div>Home</div>} />
          </Routes>
        </MemoryRouter>
      </TooltipProvider>
    </QueryClientProvider>
  )

describe('LoginPage', () => {
  // The page checks for a live session first, so these tests start signed out.
  beforeEach(() => {
    server.use(http.get('/api/user', () => jsonMessage(401, 'Unauthenticated.')))
  })

  it('sends an already signed-in visitor to the app instead of showing the form', async () => {
    server.resetHandlers()
    renderLoginRoutes()

    expect(await screen.findByText('Home')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Sign in' })).not.toBeInTheDocument()
  })

  it('shows the form when there is no session, without the client bouncing the page', async () => {
    // lib/api.ts hard-redirects on 401 by assigning location.href. jsdom cannot
    // navigate, so a stub stands in for location and records any assignment.
    const original = window.location
    const location = { href: 'https://foliotrak.test/login', pathname: '/login', reload: vi.fn() }
    vi.stubGlobal('location', location)
    expect(window.location).toBe(location)
    renderLoginRoutes()

    expect(await screen.findByRole('button', { name: 'Sign in' })).toBeInTheDocument()
    expect(location.href).toBe('https://foliotrak.test/login')
    // Only location goes back; unstubAllGlobals would also drop the setup file's observers.
    vi.stubGlobal('location', original)
  })

  it('runs the real csrf-then-login sequence and navigates home on success', async () => {
    renderLoginRoutes()

    await userEvent.type(await screen.findByLabelText(/email/i), 'plants@example.com')
    await userEvent.type(screen.getByLabelText(/password/i), 'correct-horse')
    await userEvent.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(await screen.findByText('Home')).toBeInTheDocument()
  })

  it('shows the real 401 error and stays on the login page', async () => {
    renderLoginRoutes()

    await userEvent.type(await screen.findByLabelText(/email/i), 'wrong@example.com')
    await userEvent.type(screen.getByLabelText(/password/i), 'whatever')
    await userEvent.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(await screen.findByText('Incorrect email or password.')).toBeInTheDocument()
    expect(screen.queryByText('Home')).not.toBeInTheDocument()
  })

  // Rendered through AppRoot on purpose: the login route lives outside the shell,
  // so a hand-built wrapper here can provide context the real app does not.
  it('shows validation errors when submitting empty credentials', async () => {
    window.history.pushState({}, '', '/login')
    render(<AppRoot />)

    await userEvent.click(await screen.findByRole('button', { name: 'Sign in' }))

    expect(await screen.findByText('Enter a valid email')).toBeInTheDocument()
    expect(screen.getByText('Enter your password')).toBeInTheDocument()
    window.history.pushState({}, '', '/')
  })

  it('sends remember flag when the toggle is checked', async () => {
    let capturedBody: Record<string, unknown> | null = null
    server.use(
      http.post('/login', async ({ request }) => {
        capturedBody = (await request.json()) as Record<string, unknown>
        return HttpResponse.json(loginSuccess, { status: 200 })
      })
    )

    renderLoginRoutes()

    await userEvent.type(await screen.findByLabelText(/email/i), 'plants@example.com')
    await userEvent.type(screen.getByLabelText(/password/i), 'correct-horse')
    await userEvent.click(screen.getByLabelText(/remember me/i))
    await userEvent.click(screen.getByRole('button', { name: 'Sign in' }))

    await screen.findByText('Home')
    expect(capturedBody).toMatchObject({ remember: true })
  })
})

// Observes the gate's query from inside its children: once it reports the
// refetch outcome, the gate has re-rendered on that same state, which gives the
// refetch test a condition to wait on instead of a timer.
function AuthStatusProbe() {
  const { status } = useQuery({ queryKey: ['auth-user'], queryFn: getUser, retry: false })
  return <span>auth:{status}</span>
}

describe('AuthGate', () => {
  const renderGate = (qc: QueryClient, probe = false) =>
    render(
      <QueryClientProvider client={qc}>
        <MemoryRouter initialEntries={['/']}>
          <Routes>
            <Route path="/login" element={<div>Login screen</div>} />
            <Route
              path="/*"
              element={
                <AuthGate>
                  <div>Protected</div>
                  {probe && <AuthStatusProbe />}
                </AuthGate>
              }
            />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    )

  const client = () => new QueryClient({ defaultOptions: { queries: { retry: false } } })

  it('keeps the app up when a background refetch of the session check fails', async () => {
    const qc = client()
    renderGate(qc, true)
    expect(await screen.findByText('auth:success')).toBeInTheDocument()

    server.use(http.get('/api/user', () => HttpResponse.error()))
    await qc.refetchQueries({ queryKey: ['auth-user'] })

    expect(await screen.findByText('auth:error')).toBeInTheDocument()
    expect(screen.getByText('Protected')).toBeInTheDocument()
    expect(screen.queryByText('Login screen')).not.toBeInTheDocument()
  })

  it('offers a retry, not the login form, when the session check cannot reach the server', async () => {
    server.use(http.get('/api/user', () => jsonMessage(500, 'Server error.')))
    renderGate(client())

    const retry = await screen.findByRole('button', { name: 'Retry' })
    expect(screen.queryByText('Login screen')).not.toBeInTheDocument()

    server.resetHandlers()
    await userEvent.click(retry)
    expect(await screen.findByText('Protected')).toBeInTheDocument()
  })

  it('redirects to /login when the session is gone', async () => {
    server.use(http.get('/api/user', () => jsonMessage(401, 'Unauthenticated.')))
    renderGate(client())

    expect(await screen.findByText('Login screen')).toBeInTheDocument()
    expect(screen.queryByText('Protected')).not.toBeInTheDocument()
  })
})
