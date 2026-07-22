import axios from 'axios'
import { SessionExpiredError } from './errors'

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
      // Reject with a sentinel error so the calling code's catch block fires
      // and shows feedback before the redirect completes.
      return Promise.reject(new SessionExpiredError('Unauthenticated'))
    }

    if (status === 419) {
      window.location.reload()
      // Same as 401: reject so the form shows a message before the page reloads.
      return Promise.reject(new SessionExpiredError('Session expired'))
    }
  }
  return Promise.reject(error)
})

export default api
