---
title: 'a media rename moves the file after the row is committed, and skips the preview it cannot decode'
publishedAt: '2099-01-01 00:00'
parentPage: upgrade
---

**Concerns:** `pushword/core`

## Renaming a media no longer moves the file inside the flush

The move, the cache purge and the preview now run once the row is committed, so a flush that throws afterwards can no longer leave the database naming a file that was already renamed away. Nothing to do.

## A preview too large to decode is skipped instead of overrunning the memory limit

On the `gd` driver only: a master whose bitmap (width × height × 4 bytes) does not fit in what is left of `memory_limit` no longer gets its dimensions and main color read at rename time — that allocation is a fatal error, not an exception. The background `pw:image:cache` fills them in.

**Affected:** sites on `gd` with masters above ~20 Mpx and a `memory_limit` under 512M. If that background task does not run on your host, run `pw:image:cache` to fill the metadata, or raise `memory_limit`.
