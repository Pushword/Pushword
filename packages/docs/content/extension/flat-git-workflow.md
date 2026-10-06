---
title: 'Flat Git Workflow - Pushword CMS'
h1: 'Git-Integrated Content Workflow'
publishedAt: '2026-01-23 08:07'
toc: true
---

Edit content as flat files in a Git repository, kept separate from the codebase, while
production stays editable in the admin. Built on the [flat extension](/extension/flat).

## Architecture

Keep content in a Git submodule:

```
project/
├── src/                    # Application code
├── content/                # Git submodule (content repo)
│   └── your-host/
│       ├── homepage.md
│       ├── about.md
│       └── media/
└── var/
    └── flat-sync/          # Lock and state files
```

Content editors only access the content repository, never the source code.

## Webhook API

Lock production while you edit, so admin saves cannot conflict with your changes.
Requests authenticate with a user's API token: **Admin > Users**, edit the user, then
**Generate Token** in the **API Access** section and save (or `pw:user:token {email}`).

### Lock Production

```bash
curl -X POST https://prod.example.com/api/flat/lock \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "reason": "Bulk content update in progress",
    "ttl": 7200
  }'
```

- `host` (optional): Target specific host, omit to lock all hosts (global lock)
- `reason` (optional): Message shown to admin users
- `ttl` (optional): Lock duration in seconds (default: `webhook_lock_default_ttl`, 1 hour)

`409` if a webhook lock is already held.

### Unlock Production

```bash
curl -X POST https://prod.example.com/api/flat/unlock \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"host": "example.com"}'
```

Send `{}` to release the global lock. A manual or auto lock cannot be released here (`403`).

### Check Status

```bash
curl "https://prod.example.com/api/flat/status?host=example.com" \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

Omit `host` for the global lock.

```json
{
  "locked": true,
  "isWebhookLock": true,
  "remainingSeconds": 3542,
  "lockInfo": {
    "locked": true,
    "lockedAt": 1706000000,
    "lockedBy": "webhook",
    "ttl": 3600,
    "reason": "Bulk content update",
    "lockedByUser": "user@example.com"
  }
}
```

## Workflow

1. **Lock production** (`POST /api/flat/lock`)
2. **Edit flat files** in your local content repository
3. **Commit and push** to origin
4. **CI/CD** pulls the changes and runs `pw:flat:sync`
5. **Unlock production** (`POST /api/flat/unlock`)

While a webhook lock is held, content stays visible in the admin, saves are refused
(`403`), and a red banner shows the reason and who locked.

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Deploy Content
on:
  push:
    branches: [main]
    paths: ['content/**']

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Wait for unlock
        run: |
          for i in {1..30}; do
            STATUS=$(curl -s "${{ secrets.PROD_URL }}/api/flat/status" \
              -H "Authorization: Bearer ${{ secrets.FLAT_API_TOKEN }}")
            if echo "$STATUS" | jq -e '.locked == false' > /dev/null; then
              echo "Unlocked, proceeding..."
              exit 0
            fi
            echo "Locked, waiting 10s... (attempt $i/30)"
            sleep 10
          done
          echo "Timeout waiting for unlock"
          exit 1

      - name: Deploy and sync
        run: |
          ssh user@server "cd /var/www && git pull && php bin/console pw:flat:sync"
```

### GitLab CI Example

```yaml
deploy-content:
  stage: deploy
  script:
    - |
      for i in $(seq 1 30); do
        STATUS=$(curl -s "$PROD_URL/api/flat/status" -H "Authorization: Bearer $FLAT_API_TOKEN")
        if echo "$STATUS" | jq -e '.locked == false' > /dev/null; then
          echo "Proceeding with deployment..."
          break
        fi
        echo "Locked, waiting... ($i/30)"
        sleep 10
      done
    - ssh user@server "cd /var/www && git pull && php bin/console pw:flat:sync"
  only:
    changes:
      - content/**
```

## Conflicts and Notifications

When a page is modified both in the admin and in its file, the most recent wins and the
losing version is saved as `page~conflict-{id}.md` (see
[conflict resolution](/extension/flat#conflict-resolution)). Each conflict also creates an
admin notification, emailed to the configured recipients:

```yaml
# config/packages/flat.yaml
pushword_flat:
  notification_email_recipients:
    - admin@example.com
    - devops@example.com
  notification_email_from: noreply@example.com
```

**Admin > Notifications** lists conflicts, sync errors and lock info, filterable by type
and read state.

```bash
# Review, then clear conflict files
php bin/console pw:flat:conflicts:clear --dry
php bin/console pw:flat:conflicts:clear
```

Committing admin saves back to Git: see
[deferred export & git auto-commit](/extension/flat#deferred-export-git-auto-commit).
