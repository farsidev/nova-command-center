/**
 * Translation helper that is safe across Nova 4 and Nova 5.
 *
 * Nova 5 removed the global `__` helper that older tools relied on, which is the
 * root cause of the "__ is not defined" crash. This shim prefers Nova's own
 * localizer when present and otherwise falls back to the key, so the tool never
 * throws regardless of the Nova version it runs under.
 *
 * @param {string} key
 * @param {Object} [replacements]
 * @returns {string}
 */
export function __(key, replacements = {}) {
  if (typeof window !== 'undefined' && window.Nova && typeof window.Nova.__ === 'function') {
    return window.Nova.__(key, replacements)
  }

  return Object.keys(replacements).reduce(
    (line, token) => line.replace(new RegExp(`:${token}`, 'g'), replacements[token]),
    key,
  )
}

export default __
