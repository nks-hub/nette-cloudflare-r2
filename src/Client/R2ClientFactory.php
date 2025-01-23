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
    public function create(string $bucketName): R2Client
    {
        if (isset($this->clients[$bucketName])) {
            return $this->clients[$bucketName];
        }

        if (!isset($this->config->buckets[$bucketName])) {
            throw new \InvalidArgumentException("Bucket '$bucketName' is not configured.");
        }

        $bucketConfig = $this->config->buckets[$bucketName];

        $configuration = new Configuration(
            $this->config->accountId,
            $this->config->accessKeyId,
            $this->config->secretAccessKey,
            $bucketConfig->name,
            $bucketConfig->publicUrl ?? $this->config->publicUrl ?? null,
            $this->config->jurisdiction ?? null,
            $bucketConfig->storageClass ?? $this->config->upload->storageClass ?? StorageClass::STANDARD,
            $this->config->upload->cacheControl ?? null,
            $this->config->upload->chunkSize ?? 8388608,
            $this->config->upload->autoMultipart ?? true
        );

        $this->clients[$bucketName] = new R2Client($configuration);

        return $this->clients[$bucketName];
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
            $publicUrl ?? $this->config->publicUrl ?? null,
            $this->config->jurisdiction ?? null,
            $storageClass,
            $this->config->upload->cacheControl ?? null,
            $this->config->upload->chunkSize ?? 8388608,
            $this->config->upload->autoMultipart ?? true
        );

        return new R2Client($configuration);
    }
}
