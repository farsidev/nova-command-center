// A small, curated set of harmonious hues (not the full colour wheel) so
// categories are visually distinguishable without turning the rail into an
// arbitrary rainbow. Same family/saturation throughout on purpose — the goal
// is "which group is this" at a glance, not decoration.
const CATEGORY_PALETTE = [
  '#3b82f6', // blue
  '#8b5cf6', // violet
  '#14b8a6', // teal
  '#f59e0b', // amber
  '#f43f5e', // rose
  '#10b981', // emerald
  '#6366f1', // indigo
  '#06b6d4', // cyan
]

/**
 * A stable colour for a category name, picked from CATEGORY_PALETTE so the
 * same group always shows the same swatch in the rail and its section
 * header. Pure function of the name.
 */
export function categoryColor(name) {
  const label = String(name || 'General')
  let hash = 0
  for (let i = 0; i < label.length; i++) {
    // Keep the accumulator large (mod a prime near 2^31) while folding in
    // every character, and only reduce to a palette index at the very end —
    // reducing mod palette.length on every iteration would collapse most
    // names onto the same couple of colours instead of spreading them out.
    hash = (hash * 31 + label.charCodeAt(i)) % 2147483647
  }
  return CATEGORY_PALETTE[Math.abs(hash) % CATEGORY_PALETTE.length]
}
