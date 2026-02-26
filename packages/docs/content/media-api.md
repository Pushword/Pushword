---
title: 'Media Metadata API'
h1: Media Metadata API
publishedAt: '2025-02-26 12:00'
toc: true
---

A JSON API to read and update media metadata (alt text, localized alts, tags) without the admin UI. Useful for scripted workflows and external tooling.

{id=authentication}
## Authentication

All requests require a Bearer token in the `Authorization` header. The token is matched against the `apiToken` field of the `User` entity.
 

### Usage

```bash
curl -H "Authorization: Bearer your-secret-token" https://example.com/api/media/photo.jpg
```

{id=endpoints}
## Endpoints 

### GET /api/media/{filename}

Returns metadata for the given media file. Supports current filename and historical filenames (after renames).

**Response:**

```json
{
  "filename": "photo.jpg",
  "mimeType": "image/jpeg",
  "size": 204800,
  "alt": "A mountain view",
  "alts": { "fr": "Une vue de montagne" },
  "tags": ["landscape", "nature"],
  "image": {
    "width": 1920,
    "height": 1080,
    "ratio": 1.778,
    "ratioLabel": "16:9",
    "mainColor": "#3a6ea8"
  }
}
```

The `image` key is `null` for non-image media (PDF, video, etc.).

### POST /api/media/{filename}

Partial update — only the fields you send are modified.

**Updatable fields (all optional):**

| Field      | Type     | Description                                   |
| ---------- | -------- | --------------------------------------------- |
| `alt`      | string   | Main alt text                                 |
| `alts`     | object   | Localized alts (`{"fr": "..."}`)              |
| `tags`     | string[] | Tag list                                      |
| `filename` | string   | Rename the file (old name is kept in history) |

**Example:**

```bash
curl -X POST \
  -H "Authorization: Bearer your-secret-token" \
  -H "Content-Type: application/json" \
  -d '{"alt": "New alt text", "tags": ["landscape"]}' \
  https://example.com/api/media/photo.jpg
```

Returns the full updated metadata (same format as GET).

## Error Responses {id=errors}

| Status | Meaning                          |
| ------ | -------------------------------- |
| 401    | Missing or invalid Bearer token  |
| 404    | No media found for this filename |
| 400    | Invalid JSON body (POST only)    |
