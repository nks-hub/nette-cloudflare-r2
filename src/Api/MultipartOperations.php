<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Api;

use Aws\Result;
use Aws\S3\S3Client;
use NksHub\NetteCloudflareR2\Client\Configuration;
use NksHub\NetteCloudflareR2\Storage\UploadOptions;
use NksHub\NetteCloudflareR2\Url\UrlGenerator;

/**
 * Multipart upload operations for large files.
 *
 * Use multipart upload for files larger than 100MB.
 * Minimum part size is 5MB (except for the last part).
 */
class MultipartOperations
{
    private S3Client $s3Client;
    private Configuration $configuration;

    /** Minimum part size (5MB) */
    public const MIN_PART_SIZE = 5 * 1024 * 1024;

    /** Default part size (8MB) */
    public const DEFAULT_PART_SIZE = 8 * 1024 * 1024;

    /** Maximum file size (5TB) */
    public const MAX_FILE_SIZE = 5 * 1024 * 1024 * 1024 * 1024;

    public function __construct(S3Client $s3Client, Configuration $configuration)
    {
        $this->s3Client = $s3Client;
        $this->configuration = $configuration;
    }

    /**
     * Create a multipart upload.
     *
     * @param string $key Object key
     * @param UploadOptions|null $options Upload options
     * @return string Upload ID
     */
    public function create(string $key, ?UploadOptions $options = null): string
    {
        $params = [
            'Bucket' => $this->configuration->getDefaultBucket(),
            'Key' => $key,
        ];

        if ($options !== null) {
            $params = array_merge($params, $options->toAwsParams());
        }

        $result = $this->s3Client->createMultipartUpload($params);

        return $result['UploadId'];
    }

    /**
     * Upload a part.
     *
     * @param string $key Object key
     * @param string $uploadId Upload ID
     * @param int $partNumber Part number (1-10000)
     * @param string|resource $body Part content
     * @return array{ETag: string, PartNumber: int}
     */
    public function uploadPart(string $key, string $uploadId, int $partNumber, $body): array
    {
        $result = $this->s3Client->uploadPart([
            'Bucket' => $this->configuration->getDefaultBucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
            'PartNumber' => $partNumber,
            'Body' => $body,
        ]);

        return [
            'ETag' => $result['ETag'],
            'PartNumber' => $partNumber,
        ];
    }

    /**
     * Complete multipart upload.
     *
     * @param string $key Object key
     * @param string $uploadId Upload ID
     * @param array<array{ETag: string, PartNumber: int}> $parts Uploaded parts
     * @return Result AWS SDK result
     */
    public function complete(string $key, string $uploadId, array $parts): Result
    {
        // Sort parts by PartNumber
        usort($parts, fn($a, $b) => $a['PartNumber'] <=> $b['PartNumber']);

        return $this->s3Client->completeMultipartUpload([
            'Bucket' => $this->configuration->getDefaultBucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
            'MultipartUpload' => [
                'Parts' => $parts,
            ],
        ]);
    }

    /**
     * Abort multipart upload.
     *
     * @param string $key Object key
     * @param string $uploadId Upload ID
     * @return Result AWS SDK result
     */
    public function abort(string $key, string $uploadId): Result
    {
        return $this->s3Client->abortMultipartUpload([
            'Bucket' => $this->configuration->getDefaultBucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
        ]);
    }

    /**
     * List parts of an in-progress multipart upload.
     *
     * @param string $key Object key
     * @param string $uploadId Upload ID
     * @return array<array{PartNumber: int, Size: int, ETag: string}>
     */
    public function listParts(string $key, string $uploadId): array
    {
        $result = $this->s3Client->listParts([
            'Bucket' => $this->configuration->getDefaultBucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
        ]);

        $parts = [];
        if (isset($result['Parts'])) {
            foreach ($result['Parts'] as $part) {
                $parts[] = [
                    'PartNumber' => (int) $part['PartNumber'],
                    'Size' => (int) $part['Size'],
                    'ETag' => $part['ETag'],
                ];
            }
        }

        return $parts;
    }

    /**
     * List all in-progress multipart uploads for the bucket.
     *
     * @param string|null $prefix Filter by key prefix
     * @return array<array{key: string, uploadId: string, initiated: \DateTimeInterface|null}>
     */
    public function listUploads(?string $prefix = null): array
    {
        $params = [
            'Bucket' => $this->configuration->getDefaultBucket(),
        ];

        if ($prefix !== null) {
            $params['Prefix'] = $prefix;
        }

        $result = $this->s3Client->listMultipartUploads($params);

        $uploads = [];
        if (isset($result['Uploads'])) {
            foreach ($result['Uploads'] as $upload) {
                $uploads[] = [
                    'key' => $upload['Key'],
                    'uploadId' => $upload['UploadId'],
                    'initiated' => $upload['Initiated'] ?? null,
                ];
            }
        }

        return $uploads;
    }

    /**
     * Upload a large file using multipart upload.
     *
     * @param string $key Object key
     * @param string|resource $body Content to upload
     * @param UploadOptions|null $options Upload options
     * @return string Public URL
     */
    public function uploadLarge(string $key, $body, ?UploadOptions $options = null): string
    {
        $partSize = $options !== null ? $options->getChunkSize() : $this->configuration->getChunkSize();
        $partSize = max($partSize, self::MIN_PART_SIZE);

        // Create multipart upload
        $uploadId = $this->create($key, $options);

        try {
            $parts = [];
            $partNumber = 1;

            if (is_string($body)) {
                // Upload string content in chunks
                $offset = 0;
                $length = strlen($body);

                while ($offset < $length) {
                    $chunk = substr($body, $offset, $partSize);
                    $parts[] = $this->uploadPart($key, $uploadId, $partNumber, $chunk);
                    $partNumber++;
                    $offset += $partSize;
                }
            } else {
                // Upload stream in chunks
                while (!feof($body)) {
                    $chunk = fread($body, $partSize);
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    $parts[] = $this->uploadPart($key, $uploadId, $partNumber, $chunk);
                    $partNumber++;
                }
            }

            // Complete the upload
            $this->complete($key, $uploadId, $parts);

            $urlGenerator = new UrlGenerator($this->configuration);
            return $urlGenerator->getPublicUrl($key);
        } catch (\Throwable $e) {
            // Abort on failure
            $this->abort($key, $uploadId);
            throw $e;
        }
    }
}
