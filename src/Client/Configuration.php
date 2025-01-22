<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Client;

use NksHub\NetteCloudflareR2\Storage\StorageClass;

/**
 * Immutable configuration value object for R2 client.
 */
final class Configuration
{
    private string $accountId;
    private string $accessKeyId;
    private string $secretAccessKey;
    private string $defaultBucket;
    private ?string $publicUrl;
    private ?string $jurisdiction;
    private string $defaultStorageClass;
    private ?string $defaultCacheControl;
    private int $chunkSize;
    private bool $autoMultipart;

    public function __construct(
        string $accountId,
        string $accessKeyId,
        string $secretAccessKey,
        string $defaultBucket,
        ?string $publicUrl = null,
        ?string $jurisdiction = null,
        string $defaultStorageClass = StorageClass::STANDARD,
        ?string $defaultCacheControl = null,
        int $chunkSize = 8388608,
        bool $autoMultipart = true
    ) {
        $this->accountId = $accountId;
        $this->accessKeyId = $accessKeyId;
        $this->secretAccessKey = $secretAccessKey;
        $this->defaultBucket = $defaultBucket;
        $this->publicUrl = $publicUrl;
        $this->jurisdiction = $jurisdiction;
        $this->defaultStorageClass = $defaultStorageClass;
        $this->defaultCacheControl = $defaultCacheControl;
        $this->chunkSize = $chunkSize;
        $this->autoMultipart = $autoMultipart;
    }

    /**
     * Create from stdClass config (from Nette DI).
     */
    public static function fromConfig(object $config): self
    {
        return new self(
            $config->accountId,
            $config->accessKeyId,
            $config->secretAccessKey,
            $config->defaultBucket,
            $config->publicUrl ?? null,
            $config->jurisdiction ?? null,
            $config->upload->storageClass ?? StorageClass::STANDARD,
            $config->upload->cacheControl ?? null,
            $config->upload->chunkSize ?? 8388608,
            $config->upload->autoMultipart ?? true
        );
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getAccessKeyId(): string
    {
        return $this->accessKeyId;
    }

    public function getSecretAccessKey(): string
    {
        return $this->secretAccessKey;
    }

    public function getDefaultBucket(): string
    {
        return $this->defaultBucket;
    }

    public function getPublicUrl(): ?string
    {
        return $this->publicUrl;
    }

    public function getJurisdiction(): ?string
    {
        return $this->jurisdiction;
    }

    public function getDefaultStorageClass(): string
    {
        return $this->defaultStorageClass;
    }

    public function getDefaultCacheControl(): ?string
    {
        return $this->defaultCacheControl;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function isAutoMultipart(): bool
    {
        return $this->autoMultipart;
    }

    /**
     * Get R2 S3-compatible endpoint URL.
     */
    public function getEndpoint(): string
    {
        $base = $this->accountId;

        if ($this->jurisdiction !== null) {
            return "https://{$base}.{$this->jurisdiction}.r2.cloudflarestorage.com";
        }

        return "https://{$base}.r2.cloudflarestorage.com";
    }
}
