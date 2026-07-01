/**
 * A stable, pleasant colour for a category name so the same group always shows
 * the same hue in the rail and in its section header. Pure function of the name.
 */
export function categoryColor(name) {
  const label = String(name || 'General')
  let hash = 0
  for (let i = 0; i < label.length; i++) {
    hash = (hash * 31 + label.charCodeAt(i)) % 360
  }
  return `hsl(${hash}, 60%, 55%)`
}
