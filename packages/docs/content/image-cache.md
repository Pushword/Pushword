---
title: 'Image variants: on-demand generation and disk budget'
h1: 'Image cache'
publishedAt: '2026-11-15 12:00'
toc: true
---

Pushword normally generates all configured image variants after an upload. An
optional on-demand mode generates only the size and format requested by a browser,
then keeps the result in a bounded local disk cache. Existing URLs stay unchanged.

## Enable on-demand generation

```yaml
# config/packages/pushword.yaml
pushword:
    image_cache_on_demand: true
    image_cache_max_size: 134217728 # bytes: 128 MiB (the default)
```

Clear the application cache after changing configuration. Run `pw:image:cache`
once to prune variants left by the previous mode. In on-demand mode this command
refreshes media metadata and prunes the cache without warming variants, even with
`--force`. Uploads, replacements, rotations and external imports keep metadata
processing but do not copy the original into an admin preview. Their existing
background command refreshes metadata without writing variants, including for
large images whose decode is deferred out of the upload request. The admin uses
the same on-demand image routes.

Set `image_cache_max_size: 0` for on-demand generation without eviction. The budget
only applies when `image_cache_on_demand` is enabled. Keeping the default `false`
preserves eager generation and unlimited variant retention.

## Budget and eviction

The budget covers regular files directly inside configured image-filter
directories. Originals, root-level download links, symlinks, Open Graph previews
and in-progress encoder/optimizer files are excluded. Remote derivative storage
(`media_cache_is_local: false`) is not supported in on-demand mode.

After an image request, Pushword removes the oldest generated variants until the
cache fits. It uses generation time, not last access: existing files are served
directly by the web server, so PHP does not observe their reads. Concurrent
generation of the same variant is locked, and pruning skips active writers.
Temporary files and simultaneous work can briefly exceed the budget.

A variant larger than the whole budget is served and immediately evicted without
displacing smaller cached variants that fit the budget. The
response holds an open file stream, so eviction cannot interrupt its delivery.
The same URL regenerates the variant when requested again. `pw:image:optimize`
optimizes cached variants without recreating absent sizes or formats.

## CDN caching

A CDN such as Cloudflare can continue serving a variant after its local copy is
evicted. Regeneration is needed when both the CDN and local cache miss. Generated
responses are public and carry their content type, length and modification time;
configure CDN/browser cache durations through the site's usual headers or rules.

Pushword does not version image URLs, purge the CDN, or change replacement
invalidation semantics for this feature. A site accepting stale CDN copies needs
no further changes. A site requiring immediate updates owns its purge policy;
it can hook the existing Doctrine `Media` post-update/post-remove events and its
file-replacement workflow.

## Static HTML and standalone exports

HTML cache mode (`cache: static`) remains compatible: image requests still reach
the live site's media routes when the local variant is missing.

Standalone static exports require a complete media tree and are rejected while
on-demand mode is active. Before exporting, disable `image_cache_on_demand`, clear
the application cache, run `pw:image:cache`, then run `pw:static`. Keep exported
media separate from an evictable live cache.
