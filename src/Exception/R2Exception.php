<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Exception;

use RuntimeException;
use Throwable;

/**
 * Base exception for all R2-related errors.
 */
class R2Exception extends RuntimeException
{
    protected int $r2ErrorCode;
    protected int $httpStatusCode;

    public function __construct(
        string $message,
        int $r2ErrorCode = 0,
        int $httpStatusCode = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $r2ErrorCode, $previous);
        $this->r2ErrorCode = $r2ErrorCode;
        $this->httpStatusCode = $httpStatusCode;
    }

    public function getR2ErrorCode(): int
    {
        return $this->r2ErrorCode;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }
}
