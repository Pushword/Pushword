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

### `conversation/conversation.html.twig`

- Fixed invalid Tailwind color classes: `bg-red text-red` → `bg-red-100 text-red-700`
- Added `p-3 rounded` and `role="alert"` for better UX and accessibility on error div
- Removed unnecessary wrapper `<div class="flex flex-wrap">` around content block
- Removed unused `step` class and empty `row_attr`

### `conversation/review.html.twig`

- Replaced SVG inline style `style: 'width:14px'` with Tailwind class `w-3.5`
- Added `<span class="sr-only">` for edit button accessibility
- Fixed leading space in class attribute
- Removed unnecessary wrapper `<div>` around media gallery (uses `gallery_container_class` parameter instead)

## Core Package

### `component/image.html.twig` (new)

New unified template for rendering responsive `<picture>` elements, replacing `image_helper.html.twig`.

Parameters:
- `image` (Media): Required. Media object to render.
- `mode` (string): `'responsive'` (all breakpoints) or filter name (e.g. `'thumb'`, `'xs'`). Default: `'responsive'`
- `image_class` (string): CSS class for img element. Default: `'w-full h-auto mb-4'`
- `image_attr` (array): Additional img attributes. Default: `{}`
- `image_alt` (string): Alt text. Default: `image.alt`
- `lazy` (bool): Enable lazy loading. Default: `true`
- `picture_attr` (array): Picture element attributes. Default: `{style: 'margin:0'}`
- `page` (Page): Optional. Used to get locale for alt text.

### `component/image_helper.html.twig` (removed)

Functionality merged into `image.html.twig` with `mode: 'thumb'` parameter.

### `component/card.html.twig`

- Removed `image_helper.html.twig` macro import
- Replaced macro-based rendering with `image.html.twig` include

### `component/image_inline.html.twig`

- Simplified image resolution logic using `media_from_string()` directly
- Replaced internal `renderImage` macro with `image.html.twig` include
- Reduced from 124 lines to 50 lines

### `component/images_gallery.html.twig`

- Major refactoring: removed `gallery_part` macro entirely
- Replaced `image_helper.html.twig` macro import with `image.html.twig` include
- Inlined all rendering logic directly in the for loop
- Removed outer `<div>` wrapper, gallery now renders directly as `<ul>`
- Added `gallery_container_class` to default gallery class (previously only wrapped container)
- Simplified input normalization using `media_from_string()` automatic format detection
- Reduced from 105 lines to 67 lines

### `page/_content.html.twig`

- Fixed invalid Twig comment: `// if content contains...` → `{# if content contains... #}`

## Breaking Changes

### Image Template Refactoring

If you have overridden `image_helper.html.twig`, update your code:

**Before:**
```twig
{% import view('/component/image_helper.html.twig') as helper %}
{{ helper.thumb(image, page, 'thumb', {class: 'my-class'}) }}
```

**After:**
```twig
{{ include('@PushwordCore/component/image.html.twig', {
  image: media_from_string(image),
  mode: 'thumb',
  page: page,
  image_class: 'my-class'
}, with_context = false) }}
```

### Images Gallery Structure

If you have overridden `images_gallery.html.twig`, note that:
- The outer `<div class="...">` wrapper has been removed
- The `gallery_part` macro has been removed (logic is now inline)
- Use `gallery_class` parameter to apply container classes directly to the `<ul>`

**Before:**
```twig
<div class="{{ gallery_container_class|default('my-3') }}">
  <ul class="{{ gallery_class|default('grid...') }}">
```

**After:**
```twig
<ul class="{{ gallery_class|default('my-3 grid...') }}">
```

## Custom Template Overrides

If you have overridden any of these templates in your project, review your customizations against the new structure. Key changes to consider:

- `<main>`, `<header>`, `<nav>`, `<section>`, `<figure>` elements in `markdown_cheatsheet.html.twig`
- `<dl>`/`<dt>`/`<dd>` for media metadata
- Screen reader text (`sr-only`) added for accessibility
- `images_gallery.html.twig` no longer has outer `<div>` wrapper
