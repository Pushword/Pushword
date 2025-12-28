---
title: 'Media Storage Configuration'
h1: Storage
id: 48
publishedAt: '2025-12-21 21:55'
parentPage: configuration
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
      adapter: 'aws'
      options:
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
      adapter: 'ftp'
      options:
        host: 'ftp.example.com'
        username: '%env(FTP_USERNAME)%'
        password: '%env(FTP_PASSWORD)%'
        root: '/path/to/media'
```

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

- Image cache (thumbnails, optimized versions) is always stored locally in `public/{public_media_dir}/` for direct browser access
- When using remote storage, original media files are downloaded temporarily for image processing
- VichUploaderBundle is configured to use Flysystem for uploads