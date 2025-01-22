<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Storage;

/**
 * Value object for upload configuration options.
 */
final class UploadOptions
{
    private string $storageClass;
    private ?string $contentType;
    private ?string $cacheControl;
    private ?string $contentDisposition;
    private ?string $contentEncoding;
    /** @var array<string, string> */
    private array $metadata;
    private bool $autoMultipart;
    private int $chunkSize;

    /**
     * @param array<string, string> $metadata
     */
    public function __construct(
        string $storageClass = StorageClass::STANDARD,
        ?string $contentType = null,
        ?string $cacheControl = null,
        ?string $contentDisposition = null,
        ?string $contentEncoding = null,
        array $metadata = [],
        bool $autoMultipart = true,
        int $chunkSize = 8388608 // 8MB
    ) {
        $this->storageClass = $storageClass;
        $this->contentType = $contentType;
        $this->cacheControl = $cacheControl;
        $this->contentDisposition = $contentDisposition;
        $this->contentEncoding = $contentEncoding;
        $this->metadata = $metadata;
        $this->autoMultipart = $autoMultipart;
        $this->chunkSize = $chunkSize;
    }

    public static function create(): self
    {
        return new self();
    }

    public function withStorageClass(string $storageClass): self
    {
        $clone = clone $this;
        $clone->storageClass = $storageClass;
        return $clone;
    }

    public function withContentType(string $contentType): self
    {
        $clone = clone $this;
        $clone->contentType = $contentType;
        return $clone;
    }

    public function withCacheControl(string $cacheControl): self
    {
        $clone = clone $this;
        $clone->cacheControl = $cacheControl;
        return $clone;
    }

    public function withContentDisposition(string $contentDisposition): self
    {
        $clone = clone $this;
        $clone->contentDisposition = $contentDisposition;
        return $clone;
    }

    public function withContentEncoding(string $contentEncoding): self
    {
        $clone = clone $this;
        $clone->contentEncoding = $contentEncoding;
        return $clone;
    }

    /**
     * @param array<string, string> $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $clone = clone $this;
        $clone->metadata = $metadata;
        return $clone;
    }

    public function withAutoMultipart(bool $enabled): self
    {
        $clone = clone $this;
        $clone->autoMultipart = $enabled;
        return $clone;
    }

    public function withChunkSize(int $bytes): self
    {
        $clone = clone $this;
        $clone->chunkSize = $bytes;
        return $clone;
    }

    public function getStorageClass(): string
    {
        return $this->storageClass;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    public function getCacheControl(): ?string
    {
        return $this->cacheControl;
    }

    public function getContentDisposition(): ?string
    {
        return $this->contentDisposition;
    }

    public function getContentEncoding(): ?string
    {
        return $this->contentEncoding;
    }

    /**
     * @return array<string, string>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function isAutoMultipart(): bool
    {
        return $this->autoMultipart;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * Convert to AWS SDK PutObject parameters.
     *
     * @return array<string, mixed>
     */
    public function toAwsParams(): array
    {
        $params = [
            'StorageClass' => $this->storageClass,
        ];

        if ($this->contentType !== null) {
            $params['ContentType'] = $this->contentType;
        }

        if ($this->cacheControl !== null) {
            $params['CacheControl'] = $this->cacheControl;
        }

        if ($this->contentDisposition !== null) {
            $params['ContentDisposition'] = $this->contentDisposition;
        }

        if ($this->contentEncoding !== null) {
            $params['ContentEncoding'] = $this->contentEncoding;
        }

        if (!empty($this->metadata)) {
            $params['Metadata'] = $this->metadata;
        }

        return $params;
    }
}
