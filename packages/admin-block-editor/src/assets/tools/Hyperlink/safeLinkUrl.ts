export function isSafeLinkUrl(value: string): boolean {
  try {
    return ['http:', 'https:', 'mailto:', 'tel:'].includes(
      new URL(value, window.location.href).protocol,
    )
  } catch {
    return false
  }
}
