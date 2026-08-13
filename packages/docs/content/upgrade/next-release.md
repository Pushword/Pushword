---
title: 'markdown body images can optimize their sizes for display width; optional config key'
publishedAt: '2099-01-01 00:00'
parentPage: upgrade
---

**Concerns:** pushword/core

## Configure sizes for body images (optional)

Markdown images in page bodies now accept a configurable `sizes` attribute so they select appropriately-sized variants based on their actual display width, instead of defaulting to `100vw` (viewport width). On a desktop blog with article content in a ~700px column, this lets body images select `md` (992px) instead of `xl` (1600px), reducing transfer size without quality loss.

To enable: add `pushword.body_image_sizes` to your `config/packages/pushword.yaml`, or leave it unset to keep the safe default (`100vw`).

```yaml
pushword:
  body_image_sizes: "(max-width: 1023px) 100vw, 700px"
```

Format: CSS media queries compatible with `<img sizes="">`, e.g. `"(min-width: 768px) 50vw, 100vw"`. 

**Invariant:** the selected image must cover at least 2x the display width at any device-pixel-ratio to maintain quality (this was verified at 18.4 → 10.6 Mo, 80.3 → 90.6 Lighthouse on a 12-page catalog audit). Configure too narrow a column and the browser selects a smaller variant than it should, resulting in visible blur on high-DPR devices. Test your chosen value against your live template's actual column width.
