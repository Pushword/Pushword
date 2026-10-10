/**
 * The form field an editor holder feeds, named by the holder's data-input-id.
 * Looked up each time: switching modes replaces the field with a new element
 * under the same id.
 */
export function boundInputOf(
  holderId: string,
): HTMLInputElement | HTMLTextAreaElement | null {
  const holder = document.getElementById(holderId)

  return document.getElementById(holder?.getAttribute('data-input-id') || '') as
    HTMLInputElement | HTMLTextAreaElement | null
}
