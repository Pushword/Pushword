---
title: 'Media Storage Configuration'
h1: Storage
publishedAt: '2025-12-21 21:55'
toc: true
---

Pushword uses [League Flysystem](https://flysystem.thephpleague.com/) via the [flysystem-bundle](https://github.com/thephpleague/flysystem-bundle) for media storage. This allows you to store your media files locally (default) or on remote services like Amazon S3, FTP, SFTP, and more.

## Default Configuration (Local Storage)

By default, Pushword stores media files locally in your `media_dir` (configured in `pushword.yaml`):

```yaml
pushword:
  media_dir: '%kernel.project_dir%/media'
  public_media_dir: media
```

This works out of the box with no additional configuration.

## Using Remote Storage (S3, FTP, etc.)

To use a remote storage backend, you need to:

1. Install the appropriate Flysystem adapter
2. Override the Flysystem configuration in your application

### Example: Amazon S3 Storage

1. Install the S3 adapter:

```bash
composer require league/flysystem-aws-s3-v3
```

2. Create `config/packages/flysystem.yaml` in your project:

```yaml
flysystem:
  storages:
    pushword.mediaStorage:
      aws:
        client: 'aws_client_service'
        bucket: 'your-bucket-name'
        prefix: 'media'
```

3. Configure the AWS client service (see [Flysystem Bundle documentation](https://github.com/thephpleague/flysystem-bundle#amazon-s3)).

### Example: FTP Storage

1. Install the FTP adapter:

```bash
composer require league/flysystem-ftp
```

2. Create `config/packages/flysystem.yaml`:

```yaml
flysystem:
  storages:
    pushword.mediaStorage:
      ftp:
        host: 'ftp.example.com'
        username: '%env(FTP_USERNAME)%'
        password: '%env(FTP_PASSWORD)%'
        root: '/path/to/media'
```

## Cloudflare R2 originals and image cache

R2 exposes an S3-compatible API, so it uses the same Flysystem adapter. Install it first:

```bash
composer require league/flysystem-aws-s3-v3
```

Register the R2 client in `config/services.php`:

```php
use Aws\S3\S3Client;

$services->set('app.r2_client', S3Client::class)
    ->args([[
        'version' => 'latest',
        'region' => 'auto',
        'endpoint' => '%env(R2_ENDPOINT)%',
        'credentials' => [
            'key' => '%env(R2_ACCESS_KEY_ID)%',
            'secret' => '%env(R2_SECRET_ACCESS_KEY)%',
        ],
    ]]);
```

Then configure both stores in `config/packages/flysystem.yaml`. They may use the same bucket because their prefixes do not overlap:

```yaml
flysystem:
  storages:
    pushword.mediaStorage:
      aws:
        client: app.r2_client
        bucket: '%env(R2_BUCKET)%'
        prefix: originals

    pushword.mediaCacheStorage:
      aws:
        client: app.r2_client
        bucket: '%env(R2_BUCKET)%'
        prefix: cache
```

Tell Pushword that both stores are remote in `config/packages/pushword.yaml` and in `config/services.php`:

```yaml
pushword:
  media_cache_is_local: false
  # Include the Flysystem prefix when the custom domain exposes the bucket root.
  media_cache_public_url: 'https://media.example.com/cache'
```

```php
use Pushword\Core\Service\MediaStorageAdapter;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

$services->set(MediaStorageAdapter::class)
    ->args([
        '$storage' => service('pushword.mediaStorage'),
        '$mediaDir' => '%pw.media_dir%',
        '$isLocal' => false,
    ]);
```

`media_cache_public_url` is optional. With it, rendered pages reference R2 directly and legacy `/media/<filter>/<file>` requests redirect there. Without it, Symfony streams cached files from R2. The R2 custom domain must be public when a public URL is configured; the S3 API endpoint and credentials remain private.

Image processing still happens on local temporary files. Pushword uploads a derivative only after its local atomic write completes, then republishes the optimized result. `media_cache_dir` therefore remains a local working directory even when R2 is the authoritative cache.

## Advanced: Custom MediaStorageAdapter

If you need to customize how Pushword interacts with storage, you can override the `MediaStorageAdapter` service:

```php
// config/services.php
use Pushword\Core\Service\MediaStorageAdapter;

$services->set(MediaStorageAdapter::class)
    ->args([
        '$storage' => service('pushword.mediaStorage'),
        '$mediaDir' => '%pw.media_dir%',
        '$isLocal' => false, // Set to false for remote storage
    ]);
```

The `isLocal` parameter is important for performance:

- **true** (default): Uses direct filesystem paths for image processing
- **false**: Downloads files to temp directory before processing (required for remote storage)

## Available Adapters

Flysystem supports many storage backends:

| Adapter      | Package                                 |
| ------------ | --------------------------------------- |
| Local        | Built-in                                |
| Amazon S3    | `league/flysystem-aws-s3-v3`            |
| FTP          | `league/flysystem-ftp`                  |
| SFTP         | `league/flysystem-sftp-v3`              |
| Google Cloud | `league/flysystem-google-cloud-storage` |
| Azure Blob   | `league/flysystem-azure-blob-storage`   |
| Memory       | `league/flysystem-memory`               |

See the [Flysystem documentation](https://flysystem.thephpleague.com/docs/) for complete configuration options.

## Notes

- Image cache (thumbnails, optimized versions, `og/` previews) uses `pushword.mediaCacheStorage`. It is local by default, in `media_cache_dir` — `public/{public_media_dir}/` by default — and may be moved to remote Flysystem storage
- Both image writers write to a `.tmp` file beside their target and rename it into place, so a reader never meets a half-written image. A process killed mid-write (OOM, a deploy restart) leaves that file behind; `pw:image:cache` deletes the ones older than an hour on each run, under `media_dir` and `media_cache_dir`, and reports how many it took and how many were empty
- When using remote storage, original media files are downloaded temporarily for image processing
- VichUploaderBundle is configured to use Flysystem for uploads
