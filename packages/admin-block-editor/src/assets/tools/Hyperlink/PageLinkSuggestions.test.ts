import { afterEach, describe, expect, it } from 'vitest'
import PageLinkSuggestions, { PageLink } from './PageLinkSuggestions'

function setup(
  pages: PageLink[] = [
    { uri: '/blog/article-with-a-long-path', title: 'A mountain walk' },
    { uri: '/blog', title: 'Our blog' },
  ],
) {
  const input = document.createElement('input')
  const container = document.createElement('div')
  document.body.append(input, container)
  new PageLinkSuggestions(input, container, pages, (label) => label)
  return {
    input,
    container,
    async search(query: string) {
      input.value = query
      input.dispatchEvent(new Event('input'))
      await new Promise((resolve) => setTimeout(resolve, 0))
    },
    key(key: string) {
      input.dispatchEvent(
        new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }),
      )
    },
  }
}

afterEach(() => document.body.replaceChildren())

describe('page link suggestions', () => {
  it('cancels a pending search when Escape arrives before suggestions render', async () => {
    const { input, key } = setup()
    input.value = 'blog'
    input.dispatchEvent(new Event('input'))
    key('Escape')
    await new Promise((resolve) => setTimeout(resolve, 0))
    expect(input.getAttribute('aria-expanded')).toBe('false')
    expect(input.value).toBe('blog')
  })

  it('does not reopen suggestions when a pending search loses focus', async () => {
    const { input } = setup()
    input.value = 'blog'
    input.dispatchEvent(new Event('input'))
    input.dispatchEvent(new Event('blur'))
    await new Promise((resolve) => setTimeout(resolve, 0))
    expect(input.getAttribute('aria-expanded')).toBe('false')
  })

  it('uses the latest query if the keyboard arrives before the search renders', () => {
    const { input, key } = setup()
    input.value = 'blog'
    input.dispatchEvent(new Event('input'))
    key('ArrowDown')
    key('Enter')
    expect(input.value).toBe('/blog')
    expect(input.getAttribute('aria-expanded')).toBe('false')
  })

  it('shows the exact path before descendants, with the complete title and path', async () => {
    const { search, container } = setup()
    await search('blog')
    expect(
      Array.from(container.querySelectorAll('[role="option"]')).map(
        (row) => row.textContent,
      ),
    ).toEqual(['Our blog/blog', 'A mountain walk/blog/article-with-a-long-path'])
  })

  it('finds a page by title and keeps that title and its URL visible after a click', async () => {
    const { search, container, input } = setup()
    await search('MOUNTAIN')
    container.querySelector<HTMLElement>('[role="option"]')!.click()
    expect(input.value).toBe('/blog/article-with-a-long-path')
    expect(
      container.querySelector<HTMLElement>('.link-suggestions__preview')!.hidden,
    ).toBe(false)
    expect(container.querySelector('.link-suggestions__preview')!.textContent).toBe(
      'A mountain walk/blog/article-with-a-long-path',
    )
  })

  it('moves keyboard focus without changing the URL until Enter confirms the choice', async () => {
    const { search, container, input, key } = setup()
    await search('blog')
    key('ArrowDown')
    expect(input.value).toBe('blog')
    expect(container.querySelector('[aria-selected="true"]')!.textContent).toBe(
      'Our blog/blog',
    )
    expect(input.getAttribute('aria-activedescendant')).toBe(
      container.querySelector('[aria-selected="true"]')!.id,
    )
    key('Enter')
    expect(input.value).toBe('/blog')
    expect(input.getAttribute('aria-expanded')).toBe('false')
    expect(input.hasAttribute('aria-activedescendant')).toBe(false)
  })

  it('starts ArrowUp at the last result and Escape preserves the typed query', async () => {
    const { search, container, input, key } = setup()
    await search('blog')
    key('ArrowUp')
    expect(container.querySelector('[aria-selected="true"]')!.textContent).toContain(
      'A mountain walk',
    )
    key('Escape')
    expect(input.value).toBe('blog')
    expect(input.getAttribute('aria-expanded')).toBe('false')
  })

  it('cycles through results in both directions before confirming a descendant', async () => {
    const { search, container, input, key } = setup()
    await search('blog')
    const selected = () => container.querySelector('[aria-selected="true"]')!.textContent
    key('ArrowDown')
    expect(selected()).toBe('Our blog/blog')
    key('ArrowDown')
    expect(selected()).toContain('A mountain walk')
    key('ArrowDown')
    expect(selected()).toBe('Our blog/blog')
    key('ArrowUp')
    expect(selected()).toContain('A mountain walk')
    key('Enter')
    expect(input.value).toBe('/blog/article-with-a-long-path')
    expect(input.getAttribute('aria-expanded')).toBe('false')
  })

  it('handles empty and unmatched searches while allowing an external URL', async () => {
    const { search, container, input, key } = setup([])
    await search('')
    expect(input.getAttribute('aria-expanded')).toBe('false')
    await search('https://example.com/a/very/long/path')
    expect(container.querySelector('[role="status"]')!.textContent).toContain(
      'No matching page',
    )
    key('ArrowDown')
    key('Enter')
    expect(input.value).toBe('https://example.com/a/very/long/path')
    input.dispatchEvent(new Event('blur'))
    expect(container.querySelector('.link-suggestions__preview')!.textContent).toContain(
      input.value,
    )
  })

  it('ranks all candidates before limiting the list and tells the user to refine it', async () => {
    const pages = Array.from({ length: 25 }, (_, index) => ({
      uri: `/blog/${index}`,
      title: `Article ${index}`,
    }))
    pages.push({ uri: '/blog', title: 'Blog' })
    const { search, container } = setup(pages)
    await search('blog')
    expect(container.querySelectorAll('[role="option"]')).toHaveLength(20)
    expect(container.querySelector('[role="option"]')!.textContent).toBe('Blog/blog')
    expect(container.querySelector('[role="status"]')!.textContent).toContain(
      'First 20 pages',
    )
  })

  it('renders titles and paths as text, with a path fallback for untitled pages', async () => {
    const { search, container } = setup([
      { uri: '/blog/<img>', title: '<img src=x onerror=alert(1)>' },
      { uri: '/blog/untitled', title: '' },
    ])
    await search('blog')
    expect(container.querySelector('img')).toBeNull()
    expect(container.querySelector('.link-suggestions__title')!.textContent).toBe(
      '<img src=x onerror=alert(1)>',
    )
    expect(container.querySelectorAll('[role="option"]')[1].textContent).toBe(
      '/blog/untitled',
    )
  })
})
