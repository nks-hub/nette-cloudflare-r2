<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Exception;

/**
 * Exception for authentication and authorization errors (401, 403).
 *
 * R2 Error codes:
 * - 10002: Missing or invalid authentication credentials
 * - 10003: Insufficient permissions for the operation
 * - 10018: Presigned URL or request signature has expired
 * - 10035: Request signature mismatch with calculated value
 * - 10042: Account lacks entitlement to this feature
 */
class AuthenticationException extends R2Exception
{
}
