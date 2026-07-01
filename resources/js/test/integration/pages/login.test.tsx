import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http } from 'msw'
import { MemoryRouter, Routes, Route } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { LoginPage } from '@/pages/login'
import { AuthGate } from '@/components/shell/auth-gate'
import { server } from '../../handlers'
import { jsonMessage } from '../../handlers/_helpers'

const renderLoginRoutes = () =>
  render(
    <MemoryRouter initialEntries={['/login']}>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/" element={<div>Home</div>} />
      </Routes>
    </MemoryRouter>
  )

describe('LoginPage', () => {
  it('runs the real csrf-then-login sequence and navigates home on success', async () => {
    renderLoginRoutes()

    await userEvent.type(screen.getByLabelText(/email/i), 'plants@example.com')
    await userEvent.type(screen.getByLabelText(/password/i), 'correct-horse')
    await userEvent.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(await screen.findByText('Home')).toBeInTheDocument()
  })

  it('shows the real 401 error and stays on the login page', async () => {
    renderLoginRoutes()

    await userEvent.type(screen.getByLabelText(/email/i), 'wrong@example.com')
    await userEvent.type(screen.getByLabelText(/password/i), 'whatever')
    await userEvent.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(await screen.findByText('Incorrect email or password.')).toBeInTheDocument()
    expect(screen.queryByText('Home')).not.toBeInTheDocument()
  })
})

describe('AuthGate', () => {
  // A 401 here isn't usable: the shared axios instance (lib/api.ts) intercepts
  // 401s on non-auth routes and hard-redirects via window.location before React
  // Query ever sees a rejection, so AuthGate's own isError branch never runs for
  // that case. A 500 reaches AuthGate's real isError -> <Navigate> path instead.
  it('redirects to /login when the session check fails', async () => {
    server.use(http.get('/api/user', () => jsonMessage(500, 'Server error.')))
    const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })

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
                </AuthGate>
              }
            />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    )

    expect(await screen.findByText('Login screen')).toBeInTheDocument()
    expect(screen.queryByText('Protected')).not.toBeInTheDocument()
  })
})
