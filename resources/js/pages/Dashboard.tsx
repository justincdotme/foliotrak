import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import api from '../lib/api'

interface User {
  id: number
  name: string
  email: string
}

export default function Dashboard() {
  const navigate = useNavigate()

  const {
    data: user,
    isLoading,
    error,
  } = useQuery<User>({
    queryKey: ['user'],
    queryFn: async () => {
      const response = await api.get('/api/user')
      return response.data
    },
  })

  const handleLogout = async () => {
    try {
      await api.post('/logout')
    } catch (err) {
      console.error('Logout request failed:', err)
    } finally {
      navigate('/login')
    }
  }

  if (error) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="text-center">
          <h1 className="text-2xl font-semibold text-gray-900">Not authenticated</h1>
          <p className="mt-2 text-gray-600">Please log in to continue.</p>
          <button
            onClick={() => navigate('/login')}
            className="mt-4 rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
          >
            Go to Login
          </button>
        </div>
      </div>
    )
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <p className="text-gray-600">Loading...</p>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <nav className="bg-white shadow-sm">
        <div className="mx-auto max-w-6xl px-4 py-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between">
            <h1 className="text-xl font-semibold text-gray-900">Foliotrak</h1>
            <button
              onClick={handleLogout}
              className="rounded bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
            >
              Logout
            </button>
          </div>
        </div>
      </nav>

      <main className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="rounded-lg bg-white p-6 shadow-sm">
          <h2 className="text-lg font-semibold text-gray-900">Welcome, {user?.name}!</h2>
          <p className="mt-2 text-gray-600">Email: {user?.email}</p>
        </div>
      </main>
    </div>
  )
}
