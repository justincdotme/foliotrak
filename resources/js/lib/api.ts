import axios from 'axios'

// Sanctum SPA cookie auth: send the session cookie, and let axios copy the
// XSRF-TOKEN cookie into the X-XSRF-TOKEN header on every request.
const api = axios.create({
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

api.interceptors.response.use(undefined, error => {
  if (axios.isAxiosError(error) && error.response) {
    const { status, config } = error.response
    const url = config.url || ''
    const isAuthRoute = url.startsWith('/login') || url.startsWith('/sanctum/')

    if (status === 401 && !isAuthRoute) {
      window.location.href = '/login'
      return new Promise(() => {})
    }

    if (status === 419) {
      window.location.reload()
      return new Promise(() => {})
    }
  }
  return Promise.reject(error)
})

export default api
