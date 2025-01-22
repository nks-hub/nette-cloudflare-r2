<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Exception;

/**
 * Exception for bucket-related errors.
 *
 * R2 Error codes:
 * - 10005: Bucket name violates naming requirements
 * - 10006: Specified bucket does not exist
 * - 10008: Cannot delete bucket containing objects
 * - 10009: Account bucket limit exceeded (default: 1M)
 * - 10073: Bucket name already exists in account
 */
class BucketException extends R2Exception
{
}
