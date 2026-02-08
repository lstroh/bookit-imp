import axios from 'axios'

// Create axios instance with defaults.
const createApiClient = () => {
  const config = window.BOOKIT_DASHBOARD

  const client = axios.create({
    baseURL: config.apiBase,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.nonce
    },
    withCredentials: true // Send cookies for session auth.
  })

  // Response interceptor for error handling.
  client.interceptors.response.use(
    response => response,
    error => {
      if (error.response?.status === 401) {
        // Session expired - redirect to login.
        window.location.href = '/bookit-dashboard/'
        return Promise.reject(new Error('Session expired'))
      }

      const message = error.response?.data?.message || error.message || 'An error occurred'
      return Promise.reject(new Error(message))
    }
  )

  return client
}

// Export composable.
export const useApi = () => {
  const client = createApiClient()

  return {
    get: (url, config) => client.get(url, config),
    post: (url, data, config) => client.post(url, data, config),
    patch: (url, data, config) => client.patch(url, data, config),
    put: (url, data, config) => client.put(url, data, config),
    delete: (url, config) => client.delete(url, config)
  }
}
