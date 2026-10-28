import { Temporal } from 'temporal-polyfill'

// Dropping into a month/day cell keeps the original editorial wall-clock time.
export function movedDate(event, oldEvent, timeZone) {
  if (!event.allDay) return event.start.toISOString().replace(/\.\d{3}Z$/, '+00:00')
  const original = Temporal.Instant.from(oldEvent.start.toISOString()).toZonedDateTimeISO(timeZone)
  const date = Temporal.PlainDate.from(event.startStr.slice(0, 10))
  return original.with({ year: date.year, month: date.month, day: date.day }).toString({ timeZoneName: 'never', fractionalSecondDigits: 0 })
}

export async function saveMove(info, timeZone, token, fetcher = fetch) {
  const response = await fetcher(info.event.extendedProps.moveUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      _token: token,
      version: String(info.oldEvent.extendedProps.version),
      date: movedDate(info.event, info.oldEvent, timeZone),
    }),
  })
  if (!response.ok) throw new Error(response.status === 409 ? 'conflict' : 'error')
  const saved = await response.json()
  info.event.setExtendedProp('version', saved.version)
  info.event.setStart(saved.date, { allDay: false })
}
