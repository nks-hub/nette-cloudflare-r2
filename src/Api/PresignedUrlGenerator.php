<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Api;

use Aws\S3\S3Client;
use DateTimeImmutable;
use NksHub\NetteCloudflareR2\Client\Configuration;
use NksHub\NetteCloudflareR2\Storage\UploadOptions;
use NksHub\NetteCloudflareR2\Url\PresignedUrl;

/**
 * Generate presigned URLs for temporary access to R2 objects.
 *
 * Presigned URLs allow temporary access without requiring authentication.
 * Maximum expiration time is 7 days (604800 seconds).
 */
class PresignedUrlGenerator
{
    private S3Client $s3Client;
    private Configuration $configuration;

    /** Maximum expiration time (7 days) */
    public const MAX_EXPIRATION = 604800;

    public function __construct(S3Client $s3Client, Configuration $configuration)
    {
        $this->s3Client = $s3Client;
        $this->configuration = $configuration;
    }

    /**
     * Generate presigned URL for downloading (GET).
     *
     * @param string $key Object key
     * @param int $expireSeconds Expiration in seconds (max 604800)
     * @param array<string, string> $headers Response headers to set
     * @return PresignedUrl Presigned URL object
     */
    public function generateGetUrl(string $key, int $expireSeconds = 3600, array $headers = []): PresignedUrl
    {
        $expireSeconds = min($expireSeconds, self::MAX_EXPIRATION);

        $params = [
            'Bucket' => $this->configuration->getDefaultBucket(),
            'Key' => $key,
        ];

        // Add response headers if specified
        if (isset($headers['ContentType'])) {
            $params['ResponseContentType'] = $headers['ContentType'];
        }
        if (isset($headers['ContentDisposition'])) {
            $params['ResponseContentDisposition'] = $headers['ContentDisposition'];
        }
        if (isset($headers['CacheControl'])) {
            $params['ResponseCacheControl'] = $headers['CacheControl'];
        }

        $cmd = $this->s3Client->getCommand('GetObject', $params);
        $request = $this->s3Client->createPresignedRequest($cmd, "+{$expireSeconds} seconds");

        return new PresignedUrl(
            (string) $request->getUri(),
            new DateTimeImmutable("+{$expireSeconds} seconds"),
            'GET',
            $key
        );
    }

    /**
     * Generate presigned URL for uploading (PUT).
     *
     * @param string $key Object key
     * @param int $expireSeconds Expiration in seconds
     * @param UploadOptions|null $options Upload options (ContentType, etc.)
     * @return PresignedUrl Presigned URL object
     */
    public function generatePutUrl(
        string $key,
        int $expireSeconds = 3600,
        ?UploadOptions $options = null
    ): PresignedUrl {
        $expireSeconds = min($expireSeconds, self::MAX_EXPIRATION);

        $params = [
            'Bucket' => $this->configuration->getDefaultBucket(),
            'Key' => $key,
        ];

        if ($options !== null) {
            if ($options->getContentType() !== null) {
                $params['ContentType'] = $options->getContentType();
            }
            if ($options->getCacheControl() !== null) {
                $params['CacheControl'] = $options->getCacheControl();
            }
            if (!empty($options->getMetadata())) {
                $params['Metadata'] = $options->getMetadata();
            }
        }

        $cmd = $this->s3Client->getCommand('PutObject', $params);
        $request = $this->s3Client->createPresignedRequest($cmd, "+{$expireSeconds} seconds");

        return new PresignedUrl(
            (string) $request->getUri(),
            new DateTimeImmutable("+{$expireSeconds} seconds"),
            'PUT',
            $key
        );
    }

    /**
     * Generate presigned URL for deleting (DELETE).
     *
     * @param string $key Object key
     * @param int $expireSeconds Expiration in seconds
     * @return PresignedUrl Presigned URL object
     */
    public function generateDeleteUrl(string $key, int $expireSeconds = 3600): PresignedUrl
    {
        $expireSeconds = min($expireSeconds, self::MAX_EXPIRATION);

        $cmd = $this->s3Client->getCommand('DeleteObject', [
            'Bucket' => $this->configuration->getDefaultBucket(),
            'Key' => $key,
        ]);
        $request = $this->s3Client->createPresignedRequest($cmd, "+{$expireSeconds} seconds");

        return new PresignedUrl(
            (string) $request->getUri(),
            new DateTimeImmutable("+{$expireSeconds} seconds"),
            'DELETE',
            $key
        );
    }

    /**
     * Generate presigned URL for HEAD request.
     *
     * @param string $key Object key
     * @param int $expireSeconds Expiration in seconds
     * @return PresignedUrl Presigned URL object
     */
    public function generateHeadUrl(string $key, int $expireSeconds = 3600): PresignedUrl
    {
        $expireSeconds = min($expireSeconds, self::MAX_EXPIRATION);

        $cmd = $this->s3Client->getCommand('HeadObject', [
            'Bucket' => $this->configuration->getDefaultBucket(),
            'Key' => $key,
        ]);
        $request = $this->s3Client->createPresignedRequest($cmd, "+{$expireSeconds} seconds");

        return new PresignedUrl(
            (string) $request->getUri(),
            new DateTimeImmutable("+{$expireSeconds} seconds"),
            'HEAD',
            $key
        );
    }
}
