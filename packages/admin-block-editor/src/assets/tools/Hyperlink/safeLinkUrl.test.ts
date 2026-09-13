import { describe, expect, it } from 'vitest'
import { isSafeLinkUrl } from './safeLinkUrl'

describe('isSafeLinkUrl', () => {
  it.each(['/page', '#section', 'https://example.com', 'mailto:info@example.com', 'tel:+33123456789'])(
    'accepts %s', (url) => expect(isSafeLinkUrl(url)).toBe(true),
  )

  it.each(['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'http://%'])(
    'rejects %s', (url) => expect(isSafeLinkUrl(url)).toBe(false),
  )
})
