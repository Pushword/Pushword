---
title: Template Changes
h1: Template Changes (DOM Simplification)
toc: true
parent: upgrade
---

This document lists all template modifications for DOM simplification, semantic HTML improvements, and accessibility enhancements.

## Admin Package

### `markdown_cheatsheet.html.twig`

- Outer wrapper changed from `<div>` to `<main>` element
- Added `<header>` around page title
- Table of contents wrapper changed from `<div>` to `<nav>` with `aria-label`
- Each documentation section wrapped in `<section>` elements with IDs
- Example blocks changed from `<div>` to `<figure>` elements
- Removed empty HTML comments

### `media/index.html.twig`

- Media metadata changed from `<ul>`/`<li>` to semantic `<dl>`/`<dt>`/`<dd>` structure

### `admin.mediaMosaic.css`

- Updated `.media-mosaic__meta` styles for `<dl>` element (CSS grid layout)
- Added `.media-mosaic__meta dt` and `.media-mosaic__meta dd` styles
- Removed `.media-mosaic__meta li` and `.media-mosaic__meta span` styles

## Admin Block Editor Package

### `editorjs_widget.html.twig`

- Added `return false;` to onclick handlers to prevent default anchor behavior

## Conversation Package

### `admin/messageListTitleField.html.twig`

- Fixed duplicate `class` attribute (merged `class="px-1"` and `class="text-decoration-underline"`)

### `conversation/review.html.twig`

- Replaced SVG inline style `style: 'width:14px'` with Tailwind class `w-3.5`
- Added `<span class="sr-only">` for edit button accessibility
- Fixed leading space in class attribute

## Core Package

### `component/images_gallery.html.twig`

- Simplified duplicate container conditional using `wrapInContainer` variable
- No functional changes, code clarity improvement only

### `component/card.html.twig`

- Removed duplicate `{% set imageAlt = imageAlt ?? null %}` line

## Breaking Changes

**None.** All changes maintain backward compatibility.

## Custom Template Overrides

If you have overridden any of these templates in your project, review your customizations against the new structure. Key changes to consider:

- `<main>`, `<header>`, `<nav>`, `<section>`, `<figure>` elements in `markdown_cheatsheet.html.twig`
- `<dl>`/`<dt>`/`<dd>` for media metadata
- Screen reader text (`sr-only`) added for accessibility
