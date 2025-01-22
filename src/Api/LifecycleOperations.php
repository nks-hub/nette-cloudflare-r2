<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Api;

use Aws\Result;
use Aws\S3\S3Client;
use NksHub\NetteCloudflareR2\Client\Configuration;
use NksHub\NetteCloudflareR2\Storage\StorageClass;

/**
 * Lifecycle operations for R2 buckets.
 *
 * Configure automatic object expiration and storage class transitions.
 */
class LifecycleOperations
{
    private S3Client $s3Client;
    private Configuration $configuration;

    public function __construct(S3Client $s3Client, Configuration $configuration)
    {
        $this->s3Client = $s3Client;
        $this->configuration = $configuration;
    }

    /**
     * Get lifecycle configuration.
     *
     * @param string|null $bucket Bucket name
     * @return array<array<string, mixed>> Lifecycle rules
     */
    public function get(?string $bucket = null): array
    {
        try {
            $result = $this->s3Client->getBucketLifecycleConfiguration([
                'Bucket' => $bucket ?? $this->configuration->getDefaultBucket(),
            ]);

            return $result['Rules'] ?? [];
        } catch (\Exception $e) {
            // No lifecycle configuration exists
            return [];
        }
    }

    /**
     * Set lifecycle configuration.
     *
     * @param array<array<string, mixed>> $rules Lifecycle rules
     * @param string|null $bucket Bucket name
     * @return Result AWS SDK result
     */
    public function set(array $rules, ?string $bucket = null): Result
    {
        return $this->s3Client->putBucketLifecycleConfiguration([
            'Bucket' => $bucket ?? $this->configuration->getDefaultBucket(),
            'LifecycleConfiguration' => [
                'Rules' => $rules,
            ],
        ]);
    }

    /**
     * Delete lifecycle configuration.
     *
     * @param string|null $bucket Bucket name
     * @return Result AWS SDK result
     */
    public function delete(?string $bucket = null): Result
    {
        return $this->s3Client->deleteBucketLifecycle([
            'Bucket' => $bucket ?? $this->configuration->getDefaultBucket(),
        ]);
    }

    /**
     * Add an expiration rule.
     *
     * @param string $prefix Object key prefix to match
     * @param int $days Number of days until expiration
     * @param string|null $id Rule ID (auto-generated if null)
     * @return Result AWS SDK result
     */
    public function addExpirationRule(string $prefix, int $days, ?string $id = null): Result
    {
        $rules = $this->get();

        $rules[] = [
            'ID' => $id ?? 'expire-' . md5($prefix) . '-' . $days . 'd',
            'Status' => 'Enabled',
            'Filter' => [
                'Prefix' => $prefix,
            ],
            'Expiration' => [
                'Days' => $days,
            ],
        ];

        return $this->set($rules);
    }

    /**
     * Add a storage class transition rule.
     *
     * @param string $prefix Object key prefix to match
     * @param int $days Number of days until transition
     * @param string $targetClass Target storage class (STANDARD_IA)
     * @param string|null $id Rule ID
     * @return Result AWS SDK result
     */
    public function addTransitionRule(
        string $prefix,
        int $days,
        string $targetClass = StorageClass::INFREQUENT_ACCESS,
        ?string $id = null
    ): Result {
        $rules = $this->get();

        $rules[] = [
            'ID' => $id ?? 'transition-' . md5($prefix) . '-' . $days . 'd',
            'Status' => 'Enabled',
            'Filter' => [
                'Prefix' => $prefix,
            ],
            'Transitions' => [
                [
                    'Days' => $days,
                    'StorageClass' => $targetClass,
                ],
            ],
        ];

        return $this->set($rules);
    }

    /**
     * Add a rule to abort incomplete multipart uploads.
     *
     * @param int $days Days after which to abort incomplete uploads
     * @param string $prefix Optional prefix filter
     * @param string|null $id Rule ID
     * @return Result AWS SDK result
     */
    public function addAbortIncompleteMultipartRule(
        int $days = 7,
        string $prefix = '',
        ?string $id = null
    ): Result {
        $rules = $this->get();

        $rules[] = [
            'ID' => $id ?? 'abort-multipart-' . $days . 'd',
            'Status' => 'Enabled',
            'Filter' => [
                'Prefix' => $prefix,
            ],
            'AbortIncompleteMultipartUpload' => [
                'DaysAfterInitiation' => $days,
            ],
        ];

        return $this->set($rules);
    }

    /**
     * Remove a rule by ID.
     *
     * @param string $id Rule ID to remove
     * @return Result|null AWS SDK result or null if rule not found
     */
    public function removeRule(string $id): ?Result
    {
        $rules = $this->get();
        $filtered = array_filter($rules, fn($rule) => ($rule['ID'] ?? '') !== $id);

        if (count($filtered) === count($rules)) {
            return null; // Rule not found
        }

        if (empty($filtered)) {
            return $this->delete();
        }

        return $this->set(array_values($filtered));
    }

    /**
     * Build a lifecycle rule array.
     *
     * @param string $id Rule ID
     * @param string $prefix Object key prefix
     * @param int|null $expirationDays Days until expiration
     * @param int|null $transitionDays Days until transition
     * @param string|null $transitionClass Target storage class
     * @param int|null $abortMultipartDays Days to abort incomplete uploads
     * @param bool $enabled Whether rule is enabled
     * @return array<string, mixed> Rule configuration
     */
    public function buildRule(
        string $id,
        string $prefix = '',
        ?int $expirationDays = null,
        ?int $transitionDays = null,
        ?string $transitionClass = null,
        ?int $abortMultipartDays = null,
        bool $enabled = true
    ): array {
        $rule = [
            'ID' => $id,
            'Status' => $enabled ? 'Enabled' : 'Disabled',
            'Filter' => [
                'Prefix' => $prefix,
            ],
        ];

        if ($expirationDays !== null) {
            $rule['Expiration'] = ['Days' => $expirationDays];
        }

        if ($transitionDays !== null && $transitionClass !== null) {
            $rule['Transitions'] = [
                [
                    'Days' => $transitionDays,
                    'StorageClass' => $transitionClass,
                ],
            ];
        }

        if ($abortMultipartDays !== null) {
            $rule['AbortIncompleteMultipartUpload'] = [
                'DaysAfterInitiation' => $abortMultipartDays,
            ];
        }

        return $rule;
    }
}
