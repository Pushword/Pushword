---
title: 'Background Tasks'
publishedAt: '2026-01-30 15:07'
---

# Background Tasks

Pushword runs several operations as background tasks to avoid blocking HTTP requests:

- **Image cache generation** (`pw:image:cache`)
- **Image optimization** (`pw:image:optimize`)
- **PDF optimization** (`pw:pdf:optimize`)
- **Static site generation** (`pw:static`)
- **Page scanning** (`pw:page-scan`)
- **Flat file export** (`pw:flat:sync`)

## Execution Modes

Two modes are available, configured via `background_task_handler`:

### Process Mode (default)

Uses `nohup` to spawn background processes with PID-based locking. Works out of the box with no extra setup.

```yaml
# config/packages/pushword.yaml
pushword:
    background_task_handler: process
```

### Messenger Mode

Dispatches tasks onto the Symfony Messenger bus. Better suited for high-load sites or environments where direct process spawning is restricted.

```yaml
# config/packages/pushword.yaml
pushword:
    background_task_handler: messenger
```

#### Messenger Setup

1. Install the Messenger component:

```bash
composer require symfony/messenger
```

2. Configure a transport in `config/packages/messenger.yaml`:

```yaml
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            'Pushword\Core\BackgroundTask\RunCommandMessage': async
```

3. Run the worker:

```bash
php bin/console messenger:consume async
```

The HTMX-based admin polling UI works identically in both modes since existing commands handle their own PID registration and output writing.

## Locking Behavior

Commands like `pw:image:cache` use a global flock to prevent concurrent execution. When a second instance is triggered while the first is still running, the behavior depends on the execution mode:

### Process Mode

The second process **silently skips** and exits with code 0. This means work can be lost: if two different images are uploaded at the same time, only the first triggers cache generation. The second is discarded because the lock is global (not per-file).

### Messenger Mode (recommended for production)

With Messenger, tasks are queued and processed sequentially by the worker. Even if two uploads happen simultaneously, both cache generation tasks are dispatched to the message bus. The worker processes them one at a time, so **no work is lost**.

This is the main reason to prefer Messenger mode in production: it guarantees every dispatched task is eventually executed, whereas process mode can silently drop tasks under concurrent load.

```yaml
# config/packages/pushword.yaml
pushword:
    background_task_handler: messenger
```

## Media Maintenance Commands

These commands are run manually to maintain media consistency:

### Normalize filenames (`pw:media:normalize-filenames`)

Renames media files to URL-safe slugs. Use `--dry-run` to preview changes.

```bash
php bin/console pw:media:normalize-filenames --dry-run
php bin/console pw:media:normalize-filenames
```

### Clean duplicates (`pw:media:clean-duplicates`)

Detects media entries that share the same file content (SHA-1 hash) and merges them. For each duplicate group, the oldest entry (lowest ID) is kept as canonical. Duplicate filenames are added to the canonical entry's `fileNameHistory` so existing references keep resolving transparently.

Page `mainImage` references are transferred to the canonical entry. Physical files and image cache for removed duplicates are cleaned up automatically.

```bash
# Preview which media would be merged
php bin/console pw:media:clean-duplicates --dry-run

# Merge duplicates
php bin/console pw:media:clean-duplicates
```