// Count records in the actual exported file, including quoted multiline cells.
export function csvDataRows(text) {
  let quoted = false, records = 0, hasContent = false
  for (let index = 0; index < text.length; index++) {
    const character = text[index]
    if (character === '"') {
      if (quoted && text[index + 1] === '"') index++
      else quoted = !quoted
      hasContent = true
    } else if (!quoted && (character === '\n' || character === '\r')) {
      if (hasContent) records++
      hasContent = false
      if (character === '\r' && text[index + 1] === '\n') index++
    } else { hasContent = true }
  }
  if (hasContent) records++
  return Math.max(0, records - 1)
}
