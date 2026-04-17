---
title: 'Media Metadata API'
h1: Media Metadata API
publishedAt: '2025-02-26 12:00'
toc: true
---

An HTTP API to upload, read, update, and delete media files and their metadata (alt text, localized alts, tags, custom properties) without the admin UI or SFTP sync. Useful for scripted workflows and external tooling.

{id=authentication}
## Authentication

All requests require a Bearer token in the `Authorization` header. The token is matched against the `apiToken` field of the `User` entity.
 

### Usage

```bash
curl -H "Authorization: Bearer your-secret-token" https://example.com/api/media/photo.jpg
```

### From a server you already trust (SSH)

If you already have SSH access to the host, you can fetch the token on demand instead of storing it on your laptop. This avoids leaking the token via shell history, backups, or a compromised workstation — exfiltration would require compromising SSH itself.

```bash
TOKEN=$(ssh server "bin/console pw:user:token robin@example.tld")
curl -H "Authorization: Bearer $TOKEN" \
     -F "file=@./photo.jpg" \
     https://example.com/api/media/photo.jpg
```

The `pw:user:token` command writes the token raw on stdout (no newline, no decoration) so it captures cleanly through `$(…)`. Errors go to stderr and do not pollute the captured value.

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
  "hash": "aabbccdd...",
  "fileNameHistory": ["old-photo.jpg"],
  "alt": "A mountain view",
  "alts": { "fr": "Une vue de montagne" },
  "tags": ["landscape", "nature"],
  "customProperties": {},
  "image": {
    "width": 1920,
    "height": 1080,
    "ratio": 1.778,
    "ratioLabel": "16:9",
    "mainColor": "#3a6ea8"
  }
}
```

- `hash` — SHA-1 of the file content, hex-encoded. `null` if not yet computed.
- `fileNameHistory` — previous filenames after renames.
- `customProperties` — free-form key/value map set via the admin.
- `image` is `null` for non-image media (PDF, video, etc.).

### POST /api/media/{filename}

Two request shapes are supported, selected by `Content-Type`:

- `application/json` — update metadata on an existing media (404 if not found)
- `multipart/form-data` — upload a new file (creates the media)

#### JSON — metadata update

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

#### Multipart — file upload

Send the binary together with metadata. The `{filename}` in the URL is the target name; if it is already taken, Pushword auto-renames (`photo.jpg` → `photo-2.jpg`) and the response reflects the final name.

**Form fields:**

| Field   | Type         | Description                                                 |
| ------- | ------------ | ----------------------------------------------------------- |
| `file`  | file         | **Required.** The binary to upload                          |
| `alt`   | string       | Main alt text                                               |
| `alts`  | string       | Localized alts, JSON-encoded object (`{"fr": "…"}`)         |
| `tags`  | string       | Tag list, JSON-encoded array (`["landscape","nature"]`)     |

**Example:**

```bash
curl -X POST \
  -H "Authorization: Bearer your-secret-token" \
  -F "file=@./photo.jpg" \
  -F "alt=Mountain view" \
  -F 'alts={"fr":"Vue de montagne"}' \
  -F 'tags=["landscape","nature"]' \
  https://example.com/api/media/photo.jpg
```

**Responses:**

- `201 Created` + full metadata — media successfully created
- `200 OK` + metadata with `"duplicate": true` — a media with the same SHA-1 already exists; the uploaded file was discarded and the existing media is returned unchanged
- `400 Bad Request` — missing or invalid file part

### DELETE /api/media/{filename}

Deletes the media record and its file from disk. Mirrors the admin delete action: pages referencing the media as `mainImage` are updated to `null`, and the image cache is cleared.

**Example:**

```bash
curl -X DELETE \
  -H "Authorization: Bearer your-secret-token" \
  https://example.com/api/media/photo.jpg
```

**Response:** `204 No Content` on success. `404 Not Found` if the filename is unknown.

## Error Responses {id=errors}

| Status | Meaning                                             |
| ------ | --------------------------------------------------- |
| 401    | Missing or invalid Bearer token                     |
| 404    | No media found for this filename (GET / JSON POST / DELETE)  |
| 400    | Invalid JSON body, or missing/invalid upload file   |
