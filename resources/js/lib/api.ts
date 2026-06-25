import axios from 'axios'

// Sanctum SPA cookie auth: send the session cookie, and let axios copy the
// XSRF-TOKEN cookie into the X-XSRF-TOKEN header on every request.
const api = axios.create({
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

export default api
