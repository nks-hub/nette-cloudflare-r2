<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Client;

use NksHub\NetteCloudflareR2\Storage\StorageClass;

/**
 * Factory for creating R2Client instances for different buckets.
 */
class R2ClientFactory
{
    /** @var object */
    private $config;

    /** @var array<string, R2Client> */
    private array $clients = [];

    public function __construct(object $config)
    {
        $this->config = $config;
    }

    /**
     * Create client for a named bucket from configuration.
     */
    public function create(string $name): R2Client
    {
        if (isset($this->clients[$name])) {
            return $this->clients[$name];
        }

        $buckets = (array) $this->config->buckets;
        if (!isset($buckets[$name])) {
            throw new \InvalidArgumentException("Bucket '$name' is not configured.");
        }

        $bucketConfig = $buckets[$name];

        $configuration = new Configuration(
            $this->config->accountId,
            $this->config->accessKeyId,
            $this->config->secretAccessKey,
            $bucketConfig->bucket,
            $bucketConfig->publicUrl ?? null,
            $this->config->jurisdiction ?? null,
            $bucketConfig->storageClass ?? $this->config->upload->storageClass ?? StorageClass::STANDARD,
            $bucketConfig->cacheControl ?? $this->config->upload->cacheControl ?? null,
            $this->config->upload->chunkSize ?? 8388608,
            $this->config->upload->autoMultipart ?? true
        );

        $this->clients[$name] = new R2Client($configuration);

        return $this->clients[$name];
    }

    /**
     * Create client for an arbitrary bucket (not in config).
     */
    public function createForBucket(
        string $bucketName,
        ?string $publicUrl = null,
        string $storageClass = StorageClass::STANDARD
    ): R2Client {
        $configuration = new Configuration(
            $this->config->accountId,
            $this->config->accessKeyId,
            $this->config->secretAccessKey,
            $bucketName,
            $publicUrl,
            $this->config->jurisdiction ?? null,
            $storageClass,
            $this->config->upload->cacheControl ?? null,
            $this->config->upload->chunkSize ?? 8388608,
            $this->config->upload->autoMultipart ?? true
        );

        return new R2Client($configuration);
    }

    /**
     * Get all configured bucket names.
     *
     * @return string[]
     */
    public function getBucketNames(): array
    {
        return array_keys((array) $this->config->buckets);
    }

    /**
     * Check if a bucket is configured.
     */
    public function hasBucket(string $name): bool
    {
        $buckets = (array) $this->config->buckets;
        return isset($buckets[$name]);
    }
}
