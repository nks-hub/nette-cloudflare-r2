<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Client;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Generator;
use Nette\Http\FileUpload;
use NksHub\NetteCloudflareR2\Api\BucketOperations;
use NksHub\NetteCloudflareR2\Api\LifecycleOperations;
use NksHub\NetteCloudflareR2\Api\MultipartOperations;
use NksHub\NetteCloudflareR2\Api\ObjectOperations;
use NksHub\NetteCloudflareR2\Api\PresignedUrlGenerator;
use NksHub\NetteCloudflareR2\Exception\ErrorCode;
use NksHub\NetteCloudflareR2\Exception\R2Exception;
use NksHub\NetteCloudflareR2\Storage\ObjectMetadata;
use NksHub\NetteCloudflareR2\Storage\StorageClass;
use NksHub\NetteCloudflareR2\Storage\UploadOptions;
use NksHub\NetteCloudflareR2\Url\PresignedUrl;
use NksHub\NetteCloudflareR2\Url\UrlGenerator;

/**
 * Main client for Cloudflare R2 operations.
 *
 * Provides a fluent, easy-to-use API for all R2 operations including
 * upload, download, delete, copy, presigned URLs, and bucket management.
 */
class R2Client
{
    private Configuration $configuration;
    private S3Client $s3Client;
    private ?ObjectOperations $objectOperations = null;
    private ?BucketOperations $bucketOperations = null;
    private ?MultipartOperations $multipartOperations = null;
    private ?PresignedUrlGenerator $presignedGenerator = null;
    private ?LifecycleOperations $lifecycleOperations = null;
    private ?UrlGenerator $urlGenerator = null;

    /** @var array<string, mixed> */
    private array $stats = [
        'operations' => 0,
        'uploads' => 0,
        'downloads' => 0,
        'deletes' => 0,
        'bytesUploaded' => 0,
        'bytesDownloaded' => 0,
        'errors' => 0,
    ];

    public function __construct(Configuration $configuration, ?S3Client $s3Client = null)
    {
        $this->configuration = $configuration;
        $this->s3Client = $s3Client ?? $this->createS3Client();
    }

    private function createS3Client(): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => $this->configuration->getEndpoint(),
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $this->configuration->getAccessKeyId(),
                'secret' => $this->configuration->getSecretAccessKey(),
            ],
        ]);
    }

    // =========================================================================
    // UPLOAD OPERATIONS
    // =========================================================================

    /**
     * Upload content to R2.
     *
     * @param string $key Object key (path)
     * @param string|resource $body Content to upload
     * @param UploadOptions|null $options Upload options
     * @return string Public URL of uploaded object
     */
    public function upload(string $key, $body, ?UploadOptions $options = null): string
    {
        $options = $options ?? $this->getDefaultUploadOptions();
        $this->stats['operations']++;
        $this->stats['uploads']++;

        try {
            // Check if multipart upload is needed
            $size = is_string($body) ? strlen($body) : $this->getStreamSize($body);

            if ($options->isAutoMultipart() && $size > 100 * 1024 * 1024) { // 100MB
                return $this->multipart()->uploadLarge($key, $body, $options);
            }

            $this->objects()->put($key, $body, $options);

            $this->stats['bytesUploaded'] += $size;

            return $this->getPublicUrl($key);
        } catch (S3Exception $e) {
            $this->stats['errors']++;
            throw $this->convertException($e);
        }
    }

    /**
     * Upload a Nette FileUpload object.
     *
     * @param FileUpload $file Uploaded file
     * @param string $directory Target directory (prefix)
     * @param UploadOptions|null $options Upload options
     * @return string Public URL of uploaded object
     */
    public function uploadFile(FileUpload $file, string $directory = '', ?UploadOptions $options = null): string
    {
        if (!$file->isOk()) {
            throw new R2Exception('File upload failed: ' . $file->getError(), 0, 400);
        }

        $ext = pathinfo($file->getSanitizedName(), PATHINFO_EXTENSION);
        $key = $this->buildKey($directory, uniqid() . '_' . time() . '.' . $ext);

        $options = $options ?? $this->getDefaultUploadOptions();

        if ($file->getContentType() !== null) {
            $options = $options->withContentType($file->getContentType());
        }

        $stream = fopen($file->getTemporaryFile(), 'rb');
        if ($stream === false) {
            throw new R2Exception('Cannot open uploaded file', 0, 400);
        }

        try {
            return $this->upload($key, $stream, $options);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Upload from a local file path.
     *
     * @param string $key Object key
     * @param string $filePath Local file path
     * @param UploadOptions|null $options Upload options
     * @return string Public URL
     */
    public function uploadFromPath(string $key, string $filePath, ?UploadOptions $options = null): string
    {
        if (!file_exists($filePath)) {
            throw new R2Exception("File not found: $filePath", 0, 400);
        }

        $options = $options ?? $this->getDefaultUploadOptions();

        if ($options->getContentType() === null) {
            $mimeType = mime_content_type($filePath);
            if ($mimeType !== false) {
                $options = $options->withContentType($mimeType);
            }
        }

        $stream = fopen($filePath, 'rb');
        if ($stream === false) {
            throw new R2Exception("Cannot open file: $filePath", 0, 400);
        }

        try {
            return $this->upload($key, $stream, $options);
        } finally {
            fclose($stream);
        }
    }

    // =========================================================================
    // DOWNLOAD OPERATIONS
    // =========================================================================

    /**
     * Get object content as string.
     *
     * @param string $key Object key
     * @return string Object content
     */
    public function get(string $key): string
    {
        $this->stats['operations']++;
        $this->stats['downloads']++;

        try {
            $result = $this->objects()->get($key);
            $content = (string) $result['Body'];
            $this->stats['bytesDownloaded'] += strlen($content);
            return $content;
        } catch (S3Exception $e) {
            $this->stats['errors']++;
            throw $this->convertException($e);
        }
    }

    /**
     * Get object as a stream resource.
     *
     * @param string $key Object key
     * @return resource Stream resource
     */
    public function getStream(string $key)
    {
        $this->stats['operations']++;
        $this->stats['downloads']++;

        try {
            $result = $this->objects()->get($key);
            return $result['Body']->detach();
        } catch (S3Exception $e) {
            $this->stats['errors']++;
            throw $this->convertException($e);
        }
    }

    /**
     * Download object to a local file.
     *
     * @param string $key Object key
     * @param string $destination Local file path
     */
    public function download(string $key, string $destination): void
    {
        $this->stats['operations']++;
        $this->stats['downloads']++;

        try {
            $this->objects()->download($key, $destination);
            $this->stats['bytesDownloaded'] += filesize($destination) ?: 0;
        } catch (S3Exception $e) {
            $this->stats['errors']++;
            throw $this->convertException($e);
        }
    }

    // =========================================================================
    // DELETE OPERATIONS
    // =========================================================================

    /**
     * Delete a single object.
     *
     * @param string $key Object key
     */
    public function delete(string $key): void
    {
        $this->stats['operations']++;
        $this->stats['deletes']++;

        try {
            $this->objects()->delete($key);
        } catch (S3Exception $e) {
            $this->stats['errors']++;
            throw $this->convertException($e);
        }
    }

    /**
     * Delete multiple objects.
     *
     * @param string[] $keys Object keys to delete
     * @return array<string, string> Failed deletions (key => error)
     */
    public function deleteMultiple(array $keys): array
    {
        $this->stats['operations']++;
        $this->stats['deletes'] += count($keys);

        try {
            return $this->objects()->deleteMultiple($keys);
        } catch (S3Exception $e) {
            $this->stats['errors']++;
            throw $this->convertException($e);
        }
    }

    /**
     * Delete all objects with a given prefix.
     *
     * @param string $prefix Prefix to match
     * @return int Number of deleted objects
     */
    public function deleteByPrefix(string $prefix): int
    {
        $count = 0;
        $batch = [];

        foreach ($this->listAll($prefix) as $object) {
            $batch[] = $object->getKey();

            if (count($batch) >= 1000) {
                $this->deleteMultiple($batch);
                $count += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->deleteMultiple($batch);
            $count += count($batch);
        }

        return $count;
    }

    // =========================================================================
    // COPY & METADATA OPERATIONS
    // =========================================================================

    /**
     * Copy an object within the same bucket.
     *
     * @param string $sourceKey Source object key
     * @param string $destKey Destination object key
     * @param UploadOptions|null $options Options for the new object
     * @return string Public URL of the new object
     */
    public function copy(string $sourceKey, string $destKey, ?UploadOptions $options = null): string
    {
        $this->stats['operations']++;

        try {
            $this->objects()->copy($sourceKey, $destKey, $options);
            return $this->getPublicUrl($destKey);
        } catch (S3Exception $e) {
            $this->stats['errors']++;
            throw $this->convertException($e);
        }
    }

    /**
     * Check if an object exists.
     *
     * @param string $key Object key
     * @return bool True if object exists
     */
    public function exists(string $key): bool
    {
        $this->stats['operations']++;

        try {
            $this->objects()->head($key);
            return true;
        } catch (R2Exception $e) {
            if ($e->getR2ErrorCode() === ErrorCode::OBJECT_NOT_FOUND) {
                return false;
            }
            throw $e;
        } catch (S3Exception $e) {
            if ($e->getStatusCode() === 404) {
                return false;
            }
            $this->stats['errors']++;
            throw $this->convertException($e);
        }
    }

    /**
     * Get object metadata.
     *
     * @param string $key Object key
     * @return ObjectMetadata Object metadata
     */
    public function getMetadata(string $key): ObjectMetadata
    {
        $this->stats['operations']++;

        try {
            return $this->objects()->head($key);
        } catch (S3Exception $e) {
            $this->stats['errors']++;
            throw $this->convertException($e);
        }
    }

    /**
     * Update object metadata (creates a copy with new metadata).
     *
     * @param string $key Object key
     * @param array<string, string> $metadata New custom metadata
     */
    public function setMetadata(string $key, array $metadata): void
    {
        $options = UploadOptions::create()->withMetadata($metadata);
        $this->copy($key, $key, $options);
    }

    /**
     * Change object storage class.
     *
     * @param string $key Object key
     * @param string $storageClass New storage class
     */
    public function changeStorageClass(string $key, string $storageClass): void
    {
        $options = UploadOptions::create()->withStorageClass($storageClass);
        $this->copy($key, $key, $options);
    }

    // =========================================================================
    // LISTING OPERATIONS
    // =========================================================================

    /**
     * List objects with optional prefix and pagination.
     *
     * @param string $prefix Filter by prefix
     * @param int $maxKeys Maximum number of keys to return (up to 1000)
     * @param string|null $continuationToken Token for pagination
     * @return array{objects: ObjectMetadata[], isTruncated: bool, nextToken: string|null}
     */
    public function list(string $prefix = '', int $maxKeys = 1000, ?string $continuationToken = null): array
    {
        $this->stats['operations']++;

        try {
            return $this->objects()->list($prefix, $maxKeys, $continuationToken);
        } catch (S3Exception $e) {
            $this->stats['errors']++;
            throw $this->convertException($e);
        }
    }

    /**
     * List all objects with a prefix (handles pagination automatically).
     *
     * @param string $prefix Filter by prefix
     * @return Generator<ObjectMetadata>
     */
    public function listAll(string $prefix = ''): Generator
    {
        $continuationToken = null;

        do {
            $result = $this->list($prefix, 1000, $continuationToken);

            foreach ($result['objects'] as $object) {
                yield $object;
            }

            $continuationToken = $result['nextToken'];
        } while ($result['isTruncated'] && $continuationToken !== null);
    }

    /**
     * Count objects with a prefix.
     *
     * @param string $prefix Filter by prefix
     * @return int Number of objects
     */
    public function count(string $prefix = ''): int
    {
        $count = 0;
        foreach ($this->listAll($prefix) as $_) {
            $count++;
        }
        return $count;
    }

    // =========================================================================
    // URL OPERATIONS
    // =========================================================================

    /**
     * Get public URL for an object.
     *
     * @param string $key Object key
     * @return string Public URL
     */
    public function getPublicUrl(string $key): string
    {
        return $this->url()->getPublicUrl($key);
    }

    /**
     * Get presigned URL for downloading an object.
     *
     * @param string $key Object key
     * @param int $expireSeconds Expiration time in seconds (max 604800 = 7 days)
     * @return PresignedUrl Presigned URL object
     */
    public function getPresignedUrl(string $key, int $expireSeconds = 3600): PresignedUrl
    {
        $this->stats['operations']++;

        return $this->presigned()->generateGetUrl($key, $expireSeconds);
    }

    /**
     * Get presigned URL for uploading an object.
     *
     * @param string $key Object key
     * @param int $expireSeconds Expiration time in seconds
     * @param UploadOptions|null $options Upload options
     * @return PresignedUrl Presigned URL object
     */
    public function getPresignedUploadUrl(
        string $key,
        int $expireSeconds = 3600,
        ?UploadOptions $options = null
    ): PresignedUrl {
        $this->stats['operations']++;

        return $this->presigned()->generatePutUrl($key, $expireSeconds, $options);
    }

    // =========================================================================
    // BUCKET OPERATIONS
    // =========================================================================

    /**
     * Get bucket operations interface.
     *
     * @return BucketOperations
     */
    public function buckets(): BucketOperations
    {
        if ($this->bucketOperations === null) {
            $this->bucketOperations = new BucketOperations(
                $this->s3Client,
                $this->configuration
            );
        }

        return $this->bucketOperations;
    }

    /**
     * Get multipart operations interface.
     *
     * @return MultipartOperations
     */
    public function multipart(): MultipartOperations
    {
        if ($this->multipartOperations === null) {
            $this->multipartOperations = new MultipartOperations(
                $this->s3Client,
                $this->configuration
            );
        }

        return $this->multipartOperations;
    }

    /**
     * Get lifecycle operations interface.
     *
     * @return LifecycleOperations
     */
    public function lifecycle(): LifecycleOperations
    {
        if ($this->lifecycleOperations === null) {
            $this->lifecycleOperations = new LifecycleOperations(
                $this->s3Client,
                $this->configuration
            );
        }

        return $this->lifecycleOperations;
    }

    // =========================================================================
    // INTERNAL OPERATIONS
    // =========================================================================

    /**
     * Get underlying AWS S3 client for advanced operations.
     *
     * @return S3Client
     */
    public function getS3Client(): S3Client
    {
        return $this->s3Client;
    }

    /**
     * Get configuration.
     *
     * @return Configuration
     */
    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    /**
     * Get operation statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Reset operation statistics.
     */
    public function resetStats(): void
    {
        $this->stats = [
            'operations' => 0,
            'uploads' => 0,
            'downloads' => 0,
            'deletes' => 0,
            'bytesUploaded' => 0,
            'bytesDownloaded' => 0,
            'errors' => 0,
        ];
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function objects(): ObjectOperations
    {
        if ($this->objectOperations === null) {
            $this->objectOperations = new ObjectOperations(
                $this->s3Client,
                $this->configuration
            );
        }

        return $this->objectOperations;
    }

    private function presigned(): PresignedUrlGenerator
    {
        if ($this->presignedGenerator === null) {
            $this->presignedGenerator = new PresignedUrlGenerator(
                $this->s3Client,
                $this->configuration
            );
        }

        return $this->presignedGenerator;
    }

    private function url(): UrlGenerator
    {
        if ($this->urlGenerator === null) {
            $this->urlGenerator = new UrlGenerator($this->configuration);
        }

        return $this->urlGenerator;
    }

    private function getDefaultUploadOptions(): UploadOptions
    {
        $options = UploadOptions::create()
            ->withStorageClass($this->configuration->getDefaultStorageClass())
            ->withAutoMultipart($this->configuration->isAutoMultipart())
            ->withChunkSize($this->configuration->getChunkSize());

        if ($this->configuration->getDefaultCacheControl() !== null) {
            $options = $options->withCacheControl($this->configuration->getDefaultCacheControl());
        }

        return $options;
    }

    private function buildKey(string $directory, string $filename): string
    {
        $directory = trim($directory, '/');
        $filename = ltrim($filename, '/');

        if ($directory === '') {
            return $filename;
        }

        return $directory . '/' . $filename;
    }

    /**
     * @param resource $stream
     */
    private function getStreamSize($stream): int
    {
        $stat = fstat($stream);
        return $stat !== false ? ($stat['size'] ?? 0) : 0;
    }

    private function convertException(S3Exception $e): R2Exception
    {
        $statusCode = $e->getStatusCode();
        $awsCode = $e->getAwsErrorCode();
        $message = $e->getMessage();

        // Try to extract R2 error code from response
        $r2Code = 0;

        // Map common S3 errors to R2 exceptions
        $exceptionClass = ErrorCode::getExceptionClass($r2Code);

        return new $exceptionClass($message, $r2Code, $statusCode, $e);
    }
}
