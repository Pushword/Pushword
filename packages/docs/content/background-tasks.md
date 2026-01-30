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