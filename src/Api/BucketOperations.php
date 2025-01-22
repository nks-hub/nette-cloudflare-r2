<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Api;

use Aws\Result;
use Aws\S3\S3Client;
use NksHub\NetteCloudflareR2\Client\Configuration;

/**
 * Bucket operations for R2 (S3-compatible).
 */
class BucketOperations
{
    private S3Client $s3Client;
    private Configuration $configuration;

    public function __construct(S3Client $s3Client, Configuration $configuration)
    {
        $this->s3Client = $s3Client;
        $this->configuration = $configuration;
    }

    /**
     * List all buckets.
     *
     * @return array<array{name: string, creationDate: \DateTimeImmutable|null}>
     */
    public function list(): array
    {
        $result = $this->s3Client->listBuckets();

        $buckets = [];
        if (isset($result['Buckets'])) {
            foreach ($result['Buckets'] as $bucket) {
                $buckets[] = [
                    'name' => $bucket['Name'],
                    'creationDate' => $bucket['CreationDate'] ?? null,
                ];
            }
        }

        return $buckets;
    }

    /**
     * Create a new bucket.
     *
     * @param string $name Bucket name
     * @param string|null $locationHint Location hint (wnam, enam, weur, eeur, apac, oc)
     * @return Result AWS SDK result
     */
    public function create(string $name, ?string $locationHint = null): Result
    {
        $params = [
            'Bucket' => $name,
        ];

        if ($locationHint !== null) {
            $params['CreateBucketConfiguration'] = [
                'LocationConstraint' => $locationHint,
            ];
        }

        return $this->s3Client->createBucket($params);
    }

    /**
     * Delete a bucket.
     *
     * @param string $name Bucket name
     * @return Result AWS SDK result
     */
    public function delete(string $name): Result
    {
        return $this->s3Client->deleteBucket([
            'Bucket' => $name,
        ]);
    }

    /**
     * Check if bucket exists.
     *
     * @param string $name Bucket name
     * @return bool True if bucket exists
     */
    public function exists(string $name): bool
    {
        try {
            $this->s3Client->headBucket([
                'Bucket' => $name,
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get bucket location.
     *
     * @param string|null $name Bucket name (defaults to configured bucket)
     * @return string|null Location constraint
     */
    public function getLocation(?string $name = null): ?string
    {
        $result = $this->s3Client->getBucketLocation([
            'Bucket' => $name ?? $this->configuration->getDefaultBucket(),
        ]);

        return $result['LocationConstraint'] ?? null;
    }

    /**
     * Get CORS configuration.
     *
     * @param string|null $name Bucket name
     * @return array<array<string, mixed>> CORS rules
     */
    public function getCors(?string $name = null): array
    {
        try {
            $result = $this->s3Client->getBucketCors([
                'Bucket' => $name ?? $this->configuration->getDefaultBucket(),
            ]);

            return $result['CORSRules'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Set CORS configuration.
     *
     * @param array<array<string, mixed>> $rules CORS rules
     * @param string|null $name Bucket name
     * @return Result AWS SDK result
     */
    public function setCors(array $rules, ?string $name = null): Result
    {
        return $this->s3Client->putBucketCors([
            'Bucket' => $name ?? $this->configuration->getDefaultBucket(),
            'CORSConfiguration' => [
                'CORSRules' => $rules,
            ],
        ]);
    }

    /**
     * Delete CORS configuration.
     *
     * @param string|null $name Bucket name
     * @return Result AWS SDK result
     */
    public function deleteCors(?string $name = null): Result
    {
        return $this->s3Client->deleteBucketCors([
            'Bucket' => $name ?? $this->configuration->getDefaultBucket(),
        ]);
    }
}
