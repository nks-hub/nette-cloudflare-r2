<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Exception;

/**
 * Exception for rate limit errors (429).
 *
 * R2 Error codes:
 * - 10058: Rate limit exceeded (1 write/second per key)
 */
class RateLimitException extends R2Exception
{
    protected int $retryAfter;

    public function __construct(
        string $message,
        int $r2ErrorCode = 10058,
        int $httpStatusCode = 429,
        int $retryAfter = 1,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $r2ErrorCode, $httpStatusCode, $previous);
        $this->retryAfter = $retryAfter;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
