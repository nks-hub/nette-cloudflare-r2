<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Storage;

use DateTimeImmutable;

/**
 * Value object representing R2 object metadata.
 */
final class ObjectMetadata
{
    private string $key;
    private int $size;
    private ?string $etag;
    private ?DateTimeImmutable $lastModified;
    private ?string $contentType;
    private ?string $storageClass;
    /** @var array<string, string> */
    private array $customMetadata;

    /**
     * @param array<string, string> $customMetadata
     */
    public function __construct(
        string $key,
        int $size = 0,
        ?string $etag = null,
        ?DateTimeImmutable $lastModified = null,
        ?string $contentType = null,
        ?string $storageClass = null,
        array $customMetadata = []
    ) {
        $this->key = $key;
        $this->size = $size;
        $this->etag = $etag;
        $this->lastModified = $lastModified;
        $this->contentType = $contentType;
        $this->storageClass = $storageClass;
        $this->customMetadata = $customMetadata;
    }

    /**
     * Create from AWS SDK response.
     *
     * @param array<string, mixed> $data
     */
    public static function fromAwsResponse(array $data, string $key = ''): self
    {
        $lastModified = null;
        if (isset($data['LastModified'])) {
            $lastModified = $data['LastModified'] instanceof DateTimeImmutable
                ? $data['LastModified']
                : new DateTimeImmutable($data['LastModified']);
        }

        return new self(
            $data['Key'] ?? $key,
            (int) ($data['ContentLength'] ?? $data['Size'] ?? 0),
            $data['ETag'] ?? null,
            $lastModified,
            $data['ContentType'] ?? null,
            $data['StorageClass'] ?? StorageClass::STANDARD,
            $data['Metadata'] ?? []
        );
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getEtag(): ?string
    {
        return $this->etag;
    }

    public function getLastModified(): ?DateTimeImmutable
    {
        return $this->lastModified;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    public function getStorageClass(): ?string
    {
        return $this->storageClass;
    }

    /**
     * @return array<string, string>
     */
    public function getCustomMetadata(): array
    {
        return $this->customMetadata;
    }

    public function getCustomMetadataValue(string $key): ?string
    {
        return $this->customMetadata[$key] ?? null;
    }

    /**
     * Get human-readable size.
     */
    public function getFormattedSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }
}
