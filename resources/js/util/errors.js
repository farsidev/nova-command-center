import { __ } from './translate'

/**
 * Translate a run-command API error into a user-facing message, splitting out
 * per-field validation errors (`variables.foo`) so the caller can surface them
 * inline in the run modal instead of a generic toast.
 *
 * @param {Object} error - an axios-style error (reads error.response.status/data)
 * @returns {{ message: string, fieldErrors?: Object<string, string> }}
 */
export function handleRunError(error) {
  const status = error?.response?.status
  const data = error?.response?.data

  if (status === 422 && data?.errors) {
    const fieldErrors = {}
    Object.entries(data.errors).forEach(([key, messages]) => {
      const match = key.match(/^variables\.(.+)$/)
      if (match) fieldErrors[match[1]] = messages[0]
    })

    if (Object.keys(fieldErrors).length) {
      return { fieldErrors, message: data.message || __('Please fix the highlighted fields.') }
    }
  }

  if (status === 429) {
    return { message: data?.message || __('Too many commands. Please wait a moment and try again.') }
  }

  if (status === 403) {
    return { message: data?.message || __('You are not authorized to run this command.') }
  }

  return { message: data?.message || __('Command failed to start.') }
}
