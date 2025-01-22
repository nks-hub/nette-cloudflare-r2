<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Exception;

/**
 * Exception for object-related errors.
 *
 * R2 Error codes:
 * - 10007: Specified object key does not exist
 * - 10020: Object key contains invalid characters or exceeds 1024 bytes
 * - 100100: Object exceeds maximum size (5 GiB single, 5 TiB total)
 * - 10012: Custom metadata exceeds the 8,192 byte limit
 * - 10069: Object protected by bucket lock policy
 */
class ObjectException extends R2Exception
{
}
