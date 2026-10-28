import { test } from 'node:test'
import assert from 'node:assert/strict'
import { movedDate, saveMove } from './calendar-move.js'

test('a month drop preserves editorial time across the spring DST change', () => {
  const date = movedDate(
    { allDay: true, startStr: '2030-03-31' },
    { start: new Date('2030-03-30T09:30:00+01:00') },
    'Europe/Paris',
  )
  assert.equal(date, '2030-03-31T09:30:00+02:00')
})

test('a week drop keeps the selected instant, including midnight across zones', () => {
  assert.equal(movedDate({ allDay: false, start: new Date('2030-03-31T00:30:00+02:00') }, null, 'Europe/Paris'), '2030-03-30T22:30:00+00:00')
})

test('a successful move sends the original revision and adopts the saved revision', async () => {
  const changed = []
  const info = {
    oldEvent: { extendedProps: { version: 7 } },
    event: {
      allDay: false,
      start: new Date('2030-01-01T10:00:00Z'),
      extendedProps: { moveUrl: '/admin/newsletter/calendar/42/date' },
      setExtendedProp: (...args) => changed.push(args),
      setStart: (...args) => changed.push(args),
    },
  }
  await saveMove(info, 'Europe/Paris', 'csrf-token', async (url, options) => {
    assert.equal(url, '/admin/newsletter/calendar/42/date')
    assert.equal(options.body.get('version'), '7')
    assert.equal(options.body.get('_token'), 'csrf-token')
    assert.equal(options.body.get('date'), '2030-01-01T10:00:00+00:00')
    return { ok: true, json: async () => ({ version: 8, date: '2030-01-01T10:00:00+00:00' }) }
  })
  assert.deepEqual(changed, [['version', 8], ['2030-01-01T10:00:00+00:00', { allDay: false }]])
})

for (const [status, message] of [[409, 'conflict'], [403, 'error'], [500, 'error']]) {
  test(`HTTP ${status} does not update local event state`, async () => {
    const info = {
      oldEvent: { extendedProps: { version: 1 } },
      event: { allDay: false, start: new Date(), extendedProps: { moveUrl: '/date' }, setStart: () => assert.fail('must not update') },
    }
    await assert.rejects(saveMove(info, 'UTC', 'token', async () => ({ ok: false, status })), { message })
  })
}
