---
title: 'Pushword roadmap'
h1: Roadmap
publishedAt: '2025-12-23 05:48'
toc: true
---

This page records directions, not release promises. Shipped behavior belongs in the
feature and [upgrade](/upgrade) documentation.

## Current directions

- Keep optional [Rust acceleration](/native-acceleration) for measured hot paths while
  preserving the complete PHP implementation.
- Add scoped editorial permissions for multi-user, multi-site installations.
- Make pagination URLs collision-resistant, including on the homepage.
- Add bulk tag editing to admin page lists.
- Make navigation composition configurable from content.
- Expose more version-history context, including who requested a change.
