---
title: 'Media API'
h1: Media API
publishedAt: '2025-02-26 12:00'
toc: true
---

HTTP endpoints to list, upload, replace, read, update and delete media and their metadata (alt, localized alts, tags, custom properties). Shipped by [`pushword/api`](/extension/api).

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
- `422 Unprocessable Entity` — refused by a [media rule](/media-rules); nothing is created

License keys sent in `customProperties` with the file outrank the rights embedded in
it, key by key — see [image license](/image-license#upload).

### POST /api/media/{filename}/replace

Replaces the file of an **existing** media, without deleting or recreating it.
Accepts the current filename or a previous name from its history. The current name,
media ID, page associations (including `mainImage`), alt texts, tags, custom properties,
license metadata and filename history are preserved. Size, hash, modification date
and image dimensions/color are refreshed. The image cache is cleared, a quick preview
is generated immediately and full variant generation is queued, as for a new upload.

Send a multipart `file` with the **same MIME type** as the stored media. Other form
fields are ignored; use the JSON metadata endpoint for editorial changes. Embedded
rights are not reimported: this operation retains the media's existing license metadata.
Even when another media has identical bytes, the target media is kept and replaced.
Ordinary uploads to `/api/media/{filename}` still create or deduplicate; they never replace.

```bash
curl -X POST \
  -H "Authorization: Bearer your-secret-token" \
  -F "file=@./corrected-photo.jpg" \
  https://example.com/api/media/photo.jpg/replace
```

**Responses:**

- `200 OK` + full updated metadata — file replaced
- `400 Bad Request` — missing, invalid or rejected file; the original is kept
- `404 Not Found` — unknown media; nothing is created
- `422 Unprocessable Entity` — different MIME type or a [media rule](/media-rules) refused the replacement; the original is kept

### DELETE /api/media/{filename}

Deletes the media and its file. As in the admin, pages using it as `mainImage` get `null` and its image cache is cleared.

**Example:**

```bash
curl -X DELETE \
  -H "Authorization: Bearer your-secret-token" \
  https://example.com/api/media/photo.jpg
```

**Response:** `204 No Content` on success. `404 Not Found` if the filename is unknown. `409 Conflict` with `{"error", "reasons"}` when a listener [vetoes the removal](/media-rules#vetoing-a-removal); the media is kept.

## Error Responses {id=errors}

| Status | Meaning                                             |
| ------ | --------------------------------------------------- |
| 401    | Missing or invalid Bearer token                     |
| 404    | No media found for this filename (GET / metadata update / replacement / DELETE) |
| 400    | Empty or invalid JSON body, invalid `rotate`, or missing/invalid upload file |
| 409    | DELETE vetoed by a listener: `{"error": "…", "reasons": ["…"]}`  |
| 422    | Write refused by a [media rule](/media-rules), Pushword's or the app's: `{"error": "validation", "violations": [{"path": "…", "message": "…"}]}`. Nothing is saved, and a `rotate` in the same request is not applied |
