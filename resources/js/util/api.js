/**
 * Thin wrapper around Nova.request() scoped to the tool's API prefix.
 * Using Nova.request keeps CSRF, base URL and auth headers consistent with the
 * host Nova application across v4 and v5.
 */
const BASE = '/nova-vendor/farsidev/nova-command-center'

function client() {
  return window.Nova.request()
}

export default {
  commands() {
    return client().get(`${BASE}/commands`)
  },

  run(payload) {
    return client().post(`${BASE}/commands/run`, payload)
  },

  execution(id) {
    return client().get(`${BASE}/executions/${id}`)
  },

  searchVariable(commandId, variableName, query) {
    return client().get(`${BASE}/commands/${commandId}/variables/${encodeURIComponent(variableName)}/search`, {
      params: { q: query },
    })
  },

  history() {
    return client().get(`${BASE}/history`)
  },

  clearHistory() {
    return client().delete(`${BASE}/history`)
  },
}
