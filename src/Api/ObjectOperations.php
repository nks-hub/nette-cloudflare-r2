<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Api;

use Aws\Result;
use Aws\S3\S3Client;
use NksHub\NetteCloudflareR2\Client\Configuration;
use NksHub\NetteCloudflareR2\Storage\ObjectMetadata;
use NksHub\NetteCloudflareR2\Storage\UploadOptions;

/**
 * Object operations for R2 (S3-compatible).
 */
class ObjectOperations
{
    private S3Client $s3Client;
    private Configuration $configuration;

    public function __construct(S3Client $s3Client, Configuration $configuration)
    {
        $this->s3Client = $s3Client;
        $this->configuration = $configuration;
    }

    /**
     * Put (upload) an object.
     *
     * @param string $key Object key
     * @param string|resource $body Content
     * @param UploadOptions|null $options Upload options
     * @return Result AWS SDK result
     */
    public function put(string $key, $body, ?UploadOptions $options = null): Result
    {
        $params = [
            'Bucket' => $this->configuration->getDefaultBucket(),
            'Key' => $key,
            'Body' => $body,
        ];

        if ($options !== null) {
            $params = array_merge($params, $options->toAwsParams());
        }

        return $this->s3Client->putObject($params);
    }

    /**
     * Get (download) an object.
     *
     * @param string $key Object key
     * @param string|null $range Byte range (e.g., "bytes=0-999")
     * @return Result AWS SDK result with Body
     */
    public function get(string $key, ?string $range = null): Result
    {
        $params = [
            'Bucket' => $this->configuration->getDefaultBucket(),
            'Key' => $key,
        ];

        if ($range !== null) {
            $params['Range'] = $range;
        }

        return $this->s3Client->getObject($params);
    }

    /**
     * Download object to local file.
     *
     * @param string $key Object key
     * @param string $destination Local file path
     * @return Result AWS SDK result
     */
    public function download(string $key, string $destination): Result
    {
        return $this->s3Client->getObject([
            'Bucket' => $this->configuration->getDefaultBucket(),
            'Key' => $key,
            'SaveAs' => $destination,
        ]);
    }

    /**
     * Get object metadata (HEAD request).
     *
     * @param string $key Object key
     * @return ObjectMetadata Object metadata
     */
    public function head(string $key): ObjectMetadata
    {
        $result = $this->s3Client->headObject([
            'Bucket' => $this->configuration->getDefaultBucket(),
            'Key' => $key,
        ]);

        return ObjectMetadata::fromAwsResponse($result->toArray(), $key);
    }

    /**
     * Delete a single object.
     *
     * @param string $key Object key
     * @return Result AWS SDK result
     */
    public function delete(string $key): Result
    {
        return $this->s3Client->deleteObject([
            'Bucket' => $this->configuration->getDefaultBucket(),
            'Key' => $key,
        ]);
    }

    /**
     * Delete multiple objects.
     *
     * @param string[] $keys Object keys
     * @return array<string, string> Failed deletions (key => error)
     */
    public function deleteMultiple(array $keys): array
    {
        $objects = array_map(
            fn(string $key) => ['Key' => $key],
            $keys
        );

        $result = $this->s3Client->deleteObjects([
            'Bucket' => $this->configuration->getDefaultBucket(),
            'Delete' => [
                'Objects' => $objects,
                'Quiet' => false,
            ],
        ]);

        $errors = [];
        if (isset($result['Errors'])) {
            foreach ($result['Errors'] as $error) {
                $errors[$error['Key']] = $error['Message'] ?? $error['Code'] ?? 'Unknown error';
            }
        }

        return $errors;
    }

    /**
     * Copy an object.
     *
     * @param string $sourceKey Source object key
     * @param string $destKey Destination object key
     * @param UploadOptions|null $options Options for the new object
     * @param string|null $sourceBucket Source bucket (defaults to current bucket)
     * @return Result AWS SDK result
     */
    public function copy(
        string $sourceKey,
        string $destKey,
        ?UploadOptions $options = null,
        ?string $sourceBucket = null
    ): Result {
        $bucket = $this->configuration->getDefaultBucket();
        $sourceBucket = $sourceBucket ?? $bucket;

        $params = [
            'Bucket' => $bucket,
            'Key' => $destKey,
            'CopySource' => urlencode($sourceBucket . '/' . $sourceKey),
            'MetadataDirective' => $options !== null && !empty($options->getMetadata()) ? 'REPLACE' : 'COPY',
        ];

        if ($options !== null) {
            $params = array_merge($params, $options->toAwsParams());
        }

        return $this->s3Client->copyObject($params);
    }

    /**
     * List objects.
     *
     * @param string $prefix Filter by prefix
     * @param int $maxKeys Maximum number of keys (up to 1000)
     * @param string|null $continuationToken Pagination token
     * @param string|null $delimiter Delimiter for grouping
     * @return array{objects: ObjectMetadata[], isTruncated: bool, nextToken: string|null, prefixes: string[]}
     */
    public function list(
        string $prefix = '',
        int $maxKeys = 1000,
        ?string $continuationToken = null,
        ?string $delimiter = null
    ): array {
        $params = [
            'Bucket' => $this->configuration->getDefaultBucket(),
            'MaxKeys' => min($maxKeys, 1000),
        ];

        if ($prefix !== '') {
            $params['Prefix'] = $prefix;
        }

        if ($continuationToken !== null) {
            $params['ContinuationToken'] = $continuationToken;
        }

        if ($delimiter !== null) {
            $params['Delimiter'] = $delimiter;
        }

        $result = $this->s3Client->listObjectsV2($params);

        $objects = [];
        if (isset($result['Contents'])) {
            foreach ($result['Contents'] as $item) {
                $objects[] = ObjectMetadata::fromAwsResponse($item);
            }
        }

        $prefixes = [];
        if (isset($result['CommonPrefixes'])) {
            foreach ($result['CommonPrefixes'] as $prefix) {
                $prefixes[] = $prefix['Prefix'];
            }
        }

        return [
            'objects' => $objects,
            'isTruncated' => (bool) ($result['IsTruncated'] ?? false),
            'nextToken' => $result['NextContinuationToken'] ?? null,
            'prefixes' => $prefixes,
        ];
    }
}
