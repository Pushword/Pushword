---
title: Extensions
h1: 'Find your missing feature with a Pushword Extension'
publishedAt: '2025-12-21 21:55'
name: Extensions
---

- [Admin](/extension/admin)
  Manage pages, media and users through an EasyAdmin interface.
- [API](/extension/api)
  Manage pages and redirections through a token-authenticated API with an OpenAPI schema.
- [Admin Block Editor](/extension/admin-block-editor)
  Edit rich content as blocks while storing Markdown.
- [Advanced Main Image](/extension/advanced-main-image)
  Choose how each page's main image is rendered, from hidden to a full hero.
- [Conversation](/extension/conversation)
  Add comments, contact forms and other user input.
- [Flat](/extension/flat)
  Sync pages and media between the database and flat files.
- [Link Improver](/extension/link-improver)
  Add bounded, auditable internal links at render time without changing source content.
- [Newsletter](/extension/newsletter)
  Manage consent, audiences, segmented campaigns and event-driven mail sequences.
- [Page Scanner](/extension/page-scanner)
  Find broken links, redirects and TODO reminders from the command line or admin.
- [Page Update Notifier](/extension/page-update-notifier)
  Send an email when a page changes.
- [Quiz](/extension/quiz)
  Build SEO-rendered quizzes and personality tests with an optional conversion form.
- [Repurpose](/extension/repurpose)
  Render validated social carousel specs as SVG, PNG or PDF.
- [Search](/extension/search)
  Add typo-tolerant full-text search backed by a rebuildable SQLite index.
- [Snippet](/extension/snippet)
  Render reusable content fragments and developer-registered components.
- [Static Generator](/extension/static-generator)
  Export a site for GitHub Pages, Apache, FrankenPHP or Caddy.
- [Page Cache](/extension/page-cache)
  Pre-render pages for the web server while keeping dynamic routes in the app.
- [Template Editor](/extension/template-editor)
  Edit Twig templates from the admin.
- [Version](/extension/version)
  Keep, compare and restore page revisions.

To list a maintained third-party extension, [edit this page on GitHub](https://github.com/Pushword/Pushword/edit/main/packages/docs/content/extensions.md).

## Extension points

- **Events:** See constants in `Pushword\Core\Event\PushwordEvents`. Usage examples: [Admin Menu](/extension/admin-menu), [Pages List Search](/pages-list).
- **Entity filters:** Implement `Pushword\Core\Component\EntityFilter\Filter\FilterInterface`, auto-tagged as `pushword.entity_filter`.
- **Newsletter trigger sources:** Implement `Pushword\Newsletter\Trigger\TriggerSource` and tag it `pushword.newsletter.trigger_source` to start a mail sequence from anything your app watches — see [Newsletter](/extension/newsletter#custom-trigger-sources).
