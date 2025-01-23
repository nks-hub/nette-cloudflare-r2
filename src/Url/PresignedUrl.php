<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Url;

use DateTimeImmutable;

/**
 * Value object representing a presigned URL.
 */
final class PresignedUrl
{
    private string $url;
    private DateTimeImmutable $expiresAt;
    private string $method;
    private string $key;

    public function __construct(
        string $url,
        DateTimeImmutable $expiresAt,
        string $method,
        string $key
    ) {
        $this->url = $url;
        $this->expiresAt = $expiresAt;
        $this->method = $method;
        $this->key = $key;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Check if the URL has expired.
     */
    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }

    /**
     * Get seconds until expiration.
     */
    public function getSecondsUntilExpiration(): int
    {
        $now = new DateTimeImmutable();
        $diff = $this->expiresAt->getTimestamp() - $now->getTimestamp();
        return max(0, $diff);
    }

    public function __toString(): string
    {
        return $this->url;
    }
}
