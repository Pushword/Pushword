---
title: 'Insert SVG/Icon in your content - Pushword CMS'
h1: SVG
publishedAt: '2025-12-21 21:55'
toc: true
---

The `svg()` Twig function (in core) inlines an icon, from #[FontAwesome](https://fontawesome.com/icons) by default:

```twig
{{ svg('surprise') }}
```

Result: <span style="width:16px;height:16px; display:inline-block;color:var(--primary)" class="fill-current">{{ svg('surprise') }}</span>

## Configure

Set `svg_dir` (globally or per app; a list of directories searched in order) to use your own icons. Default:
- `%kernel.project_dir%/templates/icons`
- FontAwesome directories (solid, regular, brands - both free and standard)
- `%kernel.project_dir%/public/bundles/pushwordcore`