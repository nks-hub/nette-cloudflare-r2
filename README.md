# Nette Cloudflare R2

[![Latest Stable Version](https://poser.pugx.org/nks-hub/nette-cloudflare-r2/v)](https://packagist.org/packages/nks-hub/nette-cloudflare-r2)
[![Total Downloads](https://poser.pugx.org/nks-hub/nette-cloudflare-r2/downloads)](https://packagist.org/packages/nks-hub/nette-cloudflare-r2)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg)](https://php.net/)
[![Nette Version](https://img.shields.io/badge/nette-%5E3.0-blue.svg)](https://nette.org/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Cloudflare R2 storage integration for Nette Framework with full S3-compatible API support.

## Features

- 🚀 **Full S3-compatible API** - Upload, download, delete, copy, list objects
- 📦 **Multipart uploads** - Automatic chunked upload for large files
- 🔗 **Presigned URLs** - Generate temporary access URLs (up to 7 days)
- 🎯 **Nette integration** - DI extension, FileUpload support, Tracy panel
- 💾 **Storage classes** - Standard and Infrequent Access support
- 🔄 **Lifecycle rules** - Automatic expiration and transitions
- 🌐 **Cloudflare API** - Custom domains, event notifications, metrics
- 🛡️ **Type-safe** - Full PHP 7.4+ / 8.x support with strict types

## Requirements

- PHP 7.4 or higher
- Nette Framework 3.x
- Cloudflare R2 account with API credentials

## Installation

```bash
composer require nks-hub/nette-cloudflare-r2
```

## Configuration

### Basic Setup

Register the extension in your `config.neon`:

```neon
extensions:
    r2: NksHub\NetteCloudflareR2\DI\CloudflareR2Extension

r2:
    accountId: 'your-account-id'
    accessKeyId: 'your-access-key-id'
    secretAccessKey: 'your-secret-access-key'
    defaultBucket: 'my-bucket'
```

### Full Configuration

```neon
r2:
    # Required credentials
    accountId: %env.R2_ACCOUNT_ID%
    accessKeyId: %env.R2_ACCESS_KEY_ID%
    secretAccessKey: %env.R2_SECRET_ACCESS_KEY%
    defaultBucket: 'my-bucket'

    # Optional: Custom domain for public URLs
    publicUrl: 'https://cdn.example.com'

    # Optional: Jurisdictional restriction (eu, fedramp)
    jurisdiction: eu

    # Upload defaults
    upload:
        storageClass: STANDARD          # STANDARD or STANDARD_IA
        cacheControl: 'max-age=31536000'
        chunkSize: 8388608              # 8MB
        autoMultipart: true

    # Named buckets (optional)
    buckets:
        images:
            name: 'my-images-bucket'
            publicUrl: 'https://images.example.com'
        backups:
            name: 'my-backups-bucket'
            storageClass: STANDARD_IA

    # Debug options
    tracy: %debugMode%
    logging: false
```

## Usage

### Basic Operations

```php
use NksHub\NetteCloudflareR2\Client\R2Client;

class MyPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private R2Client $r2
    ) {}

    public function actionUpload(): void
    {
        // Upload string content
        $url = $this->r2->upload('path/to/file.txt', 'Hello World!');

        // Upload from local file
        $url = $this->r2->uploadFromPath('images/photo.jpg', '/local/path/photo.jpg');

        // Download content
        $content = $this->r2->get('path/to/file.txt');

        // Download to local file
        $this->r2->download('images/photo.jpg', '/local/destination.jpg');

        // Delete
        $this->r2->delete('path/to/file.txt');

        // Check existence
        if ($this->r2->exists('path/to/file.txt')) {
            // File exists
        }

        // Get metadata
        $metadata = $this->r2->getMetadata('path/to/file.txt');
        echo $metadata->getSize();
        echo $metadata->getContentType();
        echo $metadata->getFormattedSize(); // "1.5 MB"
    }
}
```

### File Upload Integration

```php
use Nette\Http\FileUpload;
use NksHub\NetteCloudflareR2\Client\R2Client;

class GalleryPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private R2Client $r2
    ) {}

    public function handleUploadPhoto(): void
    {
        /** @var FileUpload $file */
        $file = $this->getHttpRequest()->getFile('photo');

        if ($file && $file->isOk() && $file->isImage()) {
            // Upload with auto-generated filename
            $url = $this->r2->uploadFile($file, 'gallery/' . $this->user->id);

            // Save URL to database
            $this->galleryRepository->insert([
                'user_id' => $this->user->id,
                'url' => $url,
            ]);

            $this->flashMessage('Photo uploaded successfully');
        }

        $this->redirect('this');
    }
}
```

### Upload Options

```php
use NksHub\NetteCloudflareR2\Storage\UploadOptions;
use NksHub\NetteCloudflareR2\Storage\StorageClass;

// Create options
$options = UploadOptions::create()
    ->withContentType('image/jpeg')
    ->withCacheControl('max-age=86400')
    ->withStorageClass(StorageClass::INFREQUENT_ACCESS)
    ->withMetadata(['author' => 'John Doe']);

$url = $r2->upload('photo.jpg', $content, $options);
```

### Presigned URLs

```php
// Temporary download URL (1 hour)
$presignedUrl = $r2->getPresignedUrl('private/document.pdf', 3600);
echo $presignedUrl->getUrl();
echo $presignedUrl->getExpiresAt()->format('Y-m-d H:i:s');

// Temporary upload URL
$uploadUrl = $r2->getPresignedUploadUrl('uploads/new-file.jpg', 600);

// Check if expired
if ($presignedUrl->isExpired()) {
    // Generate new URL
}
```

### Listing Objects

```php
// List with pagination
$result = $r2->list('images/', maxKeys: 100);
foreach ($result['objects'] as $object) {
    echo $object->getKey();
    echo $object->getSize();
}

if ($result['isTruncated']) {
    // Get next page
    $nextResult = $r2->list('images/', 100, $result['nextToken']);
}

// List all (auto-pagination)
foreach ($r2->listAll('images/') as $object) {
    echo $object->getKey();
}

// Count objects
$count = $r2->count('images/');
```

### Multiple Buckets

```php
use NksHub\NetteCloudflareR2\Client\R2ClientFactory;

class MyService
{
    public function __construct(
        private R2Client $r2,           // Default bucket
        private R2ClientFactory $factory
    ) {}

    public function uploadToImages(string $content): string
    {
        return $this->factory->create('images')->upload('file.jpg', $content);
    }

    public function uploadToBackups(string $content): string
    {
        return $this->factory->create('backups')->upload('backup.zip', $content);
    }
}
```

### Copy and Metadata

```php
// Copy object
$newUrl = $r2->copy('original.jpg', 'copy.jpg');

// Update metadata
$r2->setMetadata('file.jpg', ['version' => '2']);

// Change storage class
$r2->changeStorageClass('archive.zip', StorageClass::INFREQUENT_ACCESS);
```

### Multipart Upload (Large Files)

```php
// Automatic multipart for large files (>100MB by default)
$url = $r2->upload('large-file.zip', $largeContent);

// Manual multipart upload
$multipart = $r2->multipart();
$uploadId = $multipart->create('huge-file.zip');

$parts = [];
$partNumber = 1;
foreach ($chunks as $chunk) {
    $parts[] = $multipart->uploadPart('huge-file.zip', $uploadId, $partNumber++, $chunk);
}

$multipart->complete('huge-file.zip', $uploadId, $parts);

// With progress tracking
use NksHub\NetteCloudflareR2\Upload\ChunkedUploader;

$uploader = new ChunkedUploader($r2);
$uploader->onProgress(function (int $uploaded, int $total) {
    echo round($uploaded / $total * 100) . '%';
});
$url = $uploader->upload('huge-file.zip', '/local/path/huge-file.zip');
```

### Lifecycle Rules

```php
// Add expiration rule (delete after 90 days)
$r2->lifecycle()->addExpirationRule('logs/', days: 90);

// Transition to Infrequent Access after 30 days
$r2->lifecycle()->addTransitionRule(
    'archive/',
    days: 30,
    targetClass: StorageClass::INFREQUENT_ACCESS
);

// Abort incomplete multipart uploads after 7 days
$r2->lifecycle()->addAbortIncompleteMultipartRule(days: 7);

// Get current rules
$rules = $r2->lifecycle()->get();

// Remove a rule
$r2->lifecycle()->removeRule('expire-logs-90d');
```

### Bucket Operations

```php
// List buckets
$buckets = $r2->buckets()->list();

// Create bucket
$r2->buckets()->create('new-bucket', locationHint: 'weur');

// Delete bucket
$r2->buckets()->delete('old-bucket');

// CORS configuration
$r2->buckets()->setCors([
    [
        'AllowedOrigins' => ['https://example.com'],
        'AllowedMethods' => ['GET', 'PUT', 'POST'],
        'AllowedHeaders' => ['*'],
        'MaxAgeSeconds' => 3600,
    ],
]);
```

### Cloudflare API (Extended Features)

```php
use NksHub\NetteCloudflareR2\Api\CloudflareApi;

$api = new CloudflareApi($accountId, $apiToken);

// Custom domains
$api->attachCustomDomain('my-bucket', 'cdn.example.com');
$api->listCustomDomains('my-bucket');

// Enable r2.dev public access
$api->setManagedDomain('my-bucket', enabled: true);

// Event notifications
$api->createEventNotification(
    'my-bucket',
    'queue-id',
    ['object-create', 'object-delete'],
    prefix: 'uploads/'
);

// Get metrics
$metrics = $api->getMetrics();

// Temporary credentials
$creds = $api->createTempCredentials(
    'my-bucket',
    permission: 'object-read-only',
    ttlSeconds: 3600
);
```

### Stream Operations

```php
use NksHub\NetteCloudflareR2\Upload\StreamUploader;
use NksHub\NetteCloudflareR2\Download\StreamDownloader;

// Upload from URL
$streamUploader = new StreamUploader($r2);
$url = $streamUploader->uploadFromUrl('image.jpg', 'https://example.com/image.jpg');

// Upload base64
$url = $streamUploader->uploadBase64('image.png', $base64Data);

// Stream download
$streamDownloader = new StreamDownloader($r2);
$streamDownloader->streamToOutput('file.pdf', $this->getHttpResponse(), 'document.pdf');

// Create FileResponse
$response = $streamDownloader->createFileResponse('file.pdf', 'download.pdf');
$this->sendResponse($response);
```

### Tracy Debugger Panel

When `tracy: true` is enabled, you'll see R2 statistics in the Tracy bar:
- Total operations count
- Uploads/downloads/deletes
- Bytes transferred
- Error count
- Configuration details

## Storage Classes

| Class | Use Case | Retrieval Fee |
|-------|----------|---------------|
| `STANDARD` | Frequently accessed data | None |
| `STANDARD_IA` | Infrequently accessed data | Yes |

```php
use NksHub\NetteCloudflareR2\Storage\StorageClass;

$options = UploadOptions::create()
    ->withStorageClass(StorageClass::INFREQUENT_ACCESS);
```

## Error Handling

```php
use NksHub\NetteCloudflareR2\Exception\R2Exception;
use NksHub\NetteCloudflareR2\Exception\ObjectException;
use NksHub\NetteCloudflareR2\Exception\AuthenticationException;
use NksHub\NetteCloudflareR2\Exception\RateLimitException;

try {
    $r2->get('non-existent-file.txt');
} catch (ObjectException $e) {
    // Object not found
    echo $e->getR2ErrorCode(); // 10007
} catch (AuthenticationException $e) {
    // Invalid credentials
} catch (RateLimitException $e) {
    // Rate limited (1 write/second per key)
    sleep($e->getRetryAfter());
} catch (R2Exception $e) {
    // Other R2 errors
}
```

## R2 Pricing Benefits

Cloudflare R2 offers significant cost savings:

| Feature | R2 | AWS S3 |
|---------|-----|--------|
| Storage | $0.015/GB | $0.023/GB |
| Egress | **$0 (FREE!)** | $0.09/GB |
| Class A ops | $4.50/million | $5.00/million |
| Class B ops | $0.36/million | $0.40/million |

**Free tier**: 10 GB storage, 1M Class A ops, 10M Class B ops per month.

## Testing

```bash
./vendor/bin/tester tests
```

## Contributing

Contributions are welcome! For major changes, please open an issue first.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'feat: description'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Support

- 📧 **Email:** dev@nks-hub.cz
- 🐛 **Bug reports:** [GitHub Issues](https://github.com/nks-hub/nette-cloudflare-r2/issues)
- 📖 **Cloudflare R2:** [cloudflare.com/products/r2](https://www.cloudflare.com/products/r2/)

## License

MIT License — see [LICENSE](LICENSE) for details.

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/nks-hub">NKS Hub</a>
</p>
