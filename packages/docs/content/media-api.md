---
title: 'Media Metadata API'
h1: Media Metadata API
publishedAt: '2025-02-26 12:00'
toc: true
---

HTTP endpoints to list, upload, read, update and delete media and their metadata (alt, localized alts, tags, custom properties). Shipped by [`pushword/api`](/extension/api).

{id=authentication}
## Authentication

Every request needs a Bearer token, matched against `User::apiToken`:

```bash
curl -H "Authorization: Bearer your-secret-token" https://example.com/api/media/photo.jpg
```

### From a server you already trust (SSH)

Fetch the token on demand over SSH instead of storing it locally:

```bash
TOKEN=$(ssh server "bin/console pw:user:token robin@example.tld")
curl -H "Authorization: Bearer $TOKEN" \
     -F "file=@./photo.jpg" \
     https://example.com/api/media/photo.jpg
```

`pw:user:token` writes the bare token to stdout (no newline) and errors to stderr, so `$(…)` captures it cleanly.

{id=endpoints}
## Endpoints

### GET /api/media

Lists media, newest first, as `{ items, total, page, per_page }` (each item in the single-media format below).

| Query param        | Effect                                        |
| ------------------ | --------------------------------------------- |
| `q` (or `search`)  | filename or alt contains the value            |
| `mimeType`         | exact MIME type                               |
| `tag`              | tags contain the value                        |
| `page`, `per_page` | pagination (default 25 per page, max 100)     |

### GET /api/media/{filename}

Returns one media's metadata. Accepts the current filename or a previous one.

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
  "licenseState": "seeded",
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
- `customProperties` — free-form key/value map. Writable (see below); keys are merged, not replaced, and a `null` value removes a key. It also holds the [image license metadata](/image-license) keys (`license`, `acquireLicensePage`, `creditText`, `creator`, `copyrightNotice`, `digitalSourceType`), which are writable like any other.
- `licenseState` — read-only: how those license properties came to be. `""` (none), `seeded` (from the app config), `overridden` (asserted by a human) or `thirdParty` (imported from the file's own embedded rights).
- `image` is `null` for non-image media (PDF, video, etc.).

### POST /api/media/{filename}

- JSON body (`POST` or `PATCH`) — update an existing media (404 if not found)
- `multipart/form-data` with a `file` part — upload a new media

#### JSON — metadata update

Only the fields you send are modified.

**Updatable fields (all optional):**

| Field              | Type     | Description                                            |
| ------------------ | -------- | ------------------------------------------------------ |
| `alt`              | string   | Main alt text                                          |
| `alts`             | object   | Localized alts (`{"fr": "..."}`)                       |
| `tags`             | string[] | Tag list                                               |
| `customProperties` | object   | Custom key/value map; keys are merged in, `null` removes a key |
| `filename`         | string   | Rename the file (old name is kept in history)          |
| `fileNameHistory`  | string[] | Replace the list of previous filenames                 |
| `rotate`           | int      | Rotate an image clockwise by a multiple of 90 degrees  |

**Example:**

```bash
curl -X POST \
  -H "Authorization: Bearer your-secret-token" \
  -H "Content-Type: application/json" \
  -d '{"alt": "New alt text", "tags": ["landscape"], "customProperties": {"credit": "Jane Doe"}}' \
  https://example.com/api/media/photo.jpg
```

Returns the full updated metadata (same format as GET).

#### Multipart — file upload

The `{filename}` in the URL is the target name; if taken, Pushword renames (`photo.jpg` → `photo-2.jpg`) and the response carries the final name.

**Form fields:**

| Field              | Type   | Description                                                     |
| ------------------ | ------ | -------------------------------------------------------------- |
| `file`             | file   | **Required.** The binary to upload                             |
| `alt`              | string | Main alt text                                                  |
| `alts`             | string | Localized alts, JSON-encoded object (`{"fr": "…"}`)            |
| `tags`             | string | Tag list, JSON-encoded array (`["landscape","nature"]`)        |
| `customProperties` | string | Custom key/value map, JSON-encoded object (`{"credit":"…"}`)   |
| `fileNameHistory`  | string | Previous filenames, JSON-encoded array                         |

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
- `400 Bad Request` — missing, invalid or rejected file

### DELETE /api/media/{filename}

Deletes the media and its file. As in the admin, pages using it as `mainImage` get `null` and its image cache is cleared.

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
| 400    | Empty or invalid JSON body, invalid `rotate`, or missing/invalid upload file |
