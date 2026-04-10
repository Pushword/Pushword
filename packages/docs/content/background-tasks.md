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
            async:
                dsn: 'doctrine://default'  # uses your existing database — no Redis needed
        routing:
            'Pushword\Core\BackgroundTask\RunCommandMessage': async
```

> For higher throughput, you can use Redis (`redis://localhost:6379/messages`) or RabbitMQ instead of the Doctrine transport.

3. Run the worker:

```bash
php bin/console messenger:consume async
```

The HTMX-based admin polling UI works identically in both modes since existing commands handle their own PID registration and output writing.

## Locking Behavior

Commands like `pw:image:cache` use per-file locks to prevent duplicate processing of the same image. When a second instance is triggered for the same file while the first is still running, it skips silently.

### Process Mode

Each upload spawns a separate background process. Different files can run concurrently without blocking each other (per-file locks). Under heavy concurrent load, many simultaneous processes may be spawned.

### Messenger Mode (recommended for production)

With Messenger, tasks are queued and processed sequentially by the worker. Even if many uploads happen simultaneously, all cache generation tasks are dispatched to the message bus. The worker processes them one at a time, ensuring controlled resource usage.

```yaml
# config/packages/pushword.yaml
pushword:
    background_task_handler: messenger
```

## Scheduled Commands

Configure commands that run automatically on specific triggers: when a scheduled page becomes published, or on a cron schedule.

```yaml
# config/packages/pushword.yaml
pushword:
    scheduled_commands:
        - { command: 'pw:static -i', on: 'publish' }
        - { command: 'pw:static', on: 'cron: 0 4 * * *' }
```

Triggers:
- `publish` — runs when a page with a future `publishedAt` date becomes published
- `cron: <expression>` — runs on a cron schedule (e.g., `cron: 0 4 * * *` for daily at 4am)

### With Messenger + Scheduler (recommended)

Everything is automatic — no system cron needed. Install the Scheduler component:

```bash
composer require symfony/scheduler
```

Then consume the Pushword schedule alongside your async transport:

```bash
php bin/console messenger:consume async scheduler_pushword
```

### Without Messenger (process mode)

For `on: publish` triggers, set up a system cron to run `pw:cron` periodically:

```cron
*/5 * * * * cd /path/to/project && php bin/console pw:cron
```

For `on: cron:` triggers in process mode, you need to set up the corresponding system crons manually.

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