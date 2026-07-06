import { describe, expect, it } from 'vitest'
import { handleRunError } from './errors'

function errorWithResponse(status, data) {
  return { response: { status, data } }
}

describe('handleRunError', () => {
  it('splits out per-field validation errors for a 422 with variables.* keys', () => {
    const result = handleRunError(errorWithResponse(422, {
      message: 'The given data was invalid.',
      errors: { 'variables.club': ['The club field is required.'] },
    }))

    expect(result.fieldErrors).toEqual({ club: 'The club field is required.' })
    expect(result.message).toBe('The given data was invalid.')
  })

  it('falls back to a generic message when a 422 has no variables.* errors', () => {
    const result = handleRunError(errorWithResponse(422, { errors: { command: ['required'] } }))

    expect(result.fieldErrors).toBeUndefined()
    expect(result.message).toBe('Command failed to start.')
  })

  it('reports a friendly message for rate limiting (429)', () => {
    expect(handleRunError(errorWithResponse(429, {})).message).toBe(
      'Too many commands. Please wait a moment and try again.',
    )
  })

  it('reports a friendly message for authorization failure (403)', () => {
    expect(handleRunError(errorWithResponse(403, {})).message).toBe(
      'You are not authorized to run this command.',
    )
  })

  it('prefers the server-provided message when present', () => {
    expect(handleRunError(errorWithResponse(429, { message: 'Slow down.' })).message).toBe('Slow down.')
  })

  it('falls back to a generic message for anything else (network error, 500, etc.)', () => {
    expect(handleRunError({}).message).toBe('Command failed to start.')
  })
})
