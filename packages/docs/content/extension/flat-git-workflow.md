---
title: 'Flat Git Workflow - Pushword CMS'
h1: 'Git-Integrated Content Workflow'
publishedAt: '2026-01-23 08:07'
toc: true
---

## Overview

Pushword supports a Git-integrated content workflow for power users who manage content through Git repositories. This allows marketing teams to edit content via flat files while keeping the codebase separate.

## Architecture

The recommended setup uses Git submodules to separate content from code:

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

Marketing teams only access the content repository, never seeing the source code.

## Webhook API

Power users can control the production lock state via curl requests.

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

**Parameters:**

- `host` (optional): Target specific host, omit to lock all hosts (global lock)
- `reason` (optional): Message shown to admin users
- `ttl` (optional): Lock duration in seconds (default: 1 hour)

### Unlock Production

```bash
curl -X POST https://prod.example.com/api/flat/unlock \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}'
```

To unlock a specific host only:

```bash
curl -X POST https://prod.example.com/api/flat/unlock \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"host": "example.com"}'
```

### Check Status

```bash
# Check global lock status
curl https://prod.example.com/api/flat/status \
  -H "Authorization: Bearer YOUR_API_TOKEN"

# Check specific host lock status
curl https://prod.example.com/api/flat/status?host=example.com \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

**Response:**

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

## API Token Setup

API tokens are managed per-user through the admin interface.

### Generate via Admin UI

1. Go to **Admin > Users** and edit the user who needs API access
2. In the **API Access** section, click **Generate Token**
3. Copy the generated token (it will be shown in the field)
4. Save the user

The token can be regenerated or revoked at any time from the same interface.

## Workflow for Power Users

### Recommended Process

1. **Lock production**: Prevents admin edits during your session

   ```bash
   curl -X POST https://prod.example.com/api/flat/lock \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"reason": "Marketing update Q1"}'
   ```

2. **Edit flat files**: Make changes in your local content repository

3. **Commit and push**: Push changes to origin

   ```bash
   git add -A && git commit -m "Update Q1 content" && git push
   ```

4. **CI/CD syncs**: Your pipeline pulls changes and runs sync

5. **Unlock production**: Release the lock
   ```bash
   curl -X POST https://prod.example.com/api/flat/unlock \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{}'
   ```

### Admin Behavior When Locked

When locked via webhook:

- **Viewing allowed**: All content remains visible
- **Editing blocked**: Save operations return 403 error
- **Banner displayed**: Red notification shows lock reason and who locked

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

## Conflict Resolution

When both admin and flat files are modified, conflicts are resolved automatically using a "most recent wins" strategy.

### Conflict Handling

1. **Backup created**: Losing version saved as `filename~conflict-{id}.md`
2. **Email sent**: Configured recipients notified
3. **Notification created**: Visible in admin notification center

### Reviewing Conflicts

```bash
# List conflict files
find content/ -name "*~conflict-*"

# Clear all conflict files after review
php bin/console pw:flat:conflicts:clear --dry-run
php bin/console pw:flat:conflicts:clear
```

## Notifications

### Email Alerts

Configure email notifications for conflicts and errors:

```yaml
# config/packages/flat.yaml
flat:
  notification_email_recipients:
    - admin@example.com
    - devops@example.com
  notification_email_from: noreply@example.com
```

### Admin Notification Center

Access via Admin > Notifications to:

- View all notifications (conflicts, sync errors, lock info)
- Filter by type
- Mark as read/unread
- Delete old notifications

## Configuration Reference

```yaml
flat:
  # Existing options
  flat_content_dir: '%kernel.project_dir%/content/_host_'
  change_detection_cache_ttl: 300
  auto_export_enabled: true
  use_background_export: true
  lock_ttl: 1800
  auto_lock_on_flat_changes: true

  # Webhook lock options
  webhook_lock_default_ttl: 3600 # Default 1 hour

  # Notification options
  notification_email_recipients: []
  notification_email_from: null

  # Auto-commit content changes to git after export (default: false)
  auto_git_commit: false
```

## Automatic Git Commits from Admin Saves

When `auto_git_commit` is enabled, admin saves are automatically committed and pushed to git:

1. Admin saves a page or media — a pending export flag is written
2. A Messenger message is dispatched with a 30-second delay (debouncing rapid saves)
3. The worker exports all pending changes in a single batch
4. Changes are committed and pushed to git automatically

A Symfony Messenger worker must be running:

```bash
php bin/console messenger:consume async -v
```

## Commands Reference

```bash
# Lock/unlock via CLI
php bin/console pw:flat:lock [host] --reason="Manual lock" --ttl=3600
php bin/console pw:flat:unlock [host]

# Manually consume pending exports
php bin/console pw:flat:sync --consume-pending

# Clear conflicts
php bin/console pw:flat:conflicts:clear [host] --dry
```