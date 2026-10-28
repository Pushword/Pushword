// @vitest-environment jsdom
import { afterEach, expect, it, vi } from 'vitest'
import { EmailBlock } from '../../../newsletter/src/Resources/assets/email-block.js'

afterEach(() => {
  document.body.innerHTML = ''
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

function block() {
  const tool = new EmailBlock({
    data: {}, readOnly: false, block: { dispatchChange: vi.fn() },
    config: {
      url: '/content', saveUrl: '/copy', token: 'token',
      labels: { title: 'Reusable content', source: 'Source', search: 'Search', content: 'Content', refresh: 'Refresh', save: 'Save copy', name: 'Name', loading: 'Loading…', failed: 'Request failed', empty: 'No matching content.' },
    },
  })
  document.body.append(tool.render())
  return tool
}

it('handles Enter like the search button, including a pending request and a network failure', async () => {
  const request = vi.fn().mockResolvedValueOnce({ ok: true, json: async () => ({ providers: { library: 'Library' } }) })
  vi.stubGlobal('fetch', request)
  const tool = block()
  await vi.waitFor(() => expect(tool.provider.options).toHaveLength(1))
  expect(tool.provider.closest('label').textContent).toContain('Source')
  expect(tool.query.closest('label').textContent).toContain('Search')
  const pending = Promise.withResolvers()
  request.mockReturnValueOnce(pending.promise)
  const search = () => tool.query.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', cancelable: true }))
  search()
  search()
  expect(request).toHaveBeenCalledTimes(2)
  expect(tool.searchButton.disabled).toBe(true)
  expect(tool.status.textContent).toBe('Loading…')
  pending.reject(new Error('Offline'))
  await vi.waitFor(() => expect(tool.status.textContent).toBe('Request failed'))
  expect(tool.searchButton.disabled).toBe(false)
  expect(tool.picker.hasAttribute('aria-busy')).toBe(false)
  request.mockResolvedValueOnce({ ok: true, json: async () => ({ items: [] }) })
  search()
  await vi.waitFor(() => expect(tool.status.textContent).toBe('No matching content.'))
  expect(tool.status.classList.contains('text-danger')).toBe(false)
})

it('selects a search result, focuses its editable copy and preserves manual changes', async () => {
  const item = { provider: 'library', reference: '42', label: 'A reusable invitation', markdown: 'Original content', parameters: {} }
  vi.stubGlobal('fetch', vi.fn()
    .mockResolvedValueOnce({ ok: true, json: async () => ({ providers: { library: 'Library' } }) })
    .mockResolvedValueOnce({ ok: true, json: async () => ({ items: [item] }) }))
  const tool = block()
  await vi.waitFor(() => expect(tool.provider.options).toHaveLength(1))
  tool.searchButton.click()
  await vi.waitFor(() => expect(tool.results.children).toHaveLength(1))
  tool.results.querySelector('button').click()
  expect(document.activeElement).toBe(tool.content)
  tool.content.value = 'Edited invitation'
  tool.content.dispatchEvent(new Event('input'))
  expect(tool.save()).toEqual({ ...item, markdown: 'Edited invitation' })
  expect(tool.block.dispatchChange).toHaveBeenCalledTimes(2)
})
