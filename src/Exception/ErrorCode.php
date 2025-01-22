<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Exception;

/**
 * Cloudflare R2 error codes mapping.
 *
 * @see https://developers.cloudflare.com/r2/api/error-codes/
 */
final class ErrorCode
{
    // Authentication & Authorization
    public const INVALID_CREDENTIALS = 10002;
    public const ACCESS_DENIED = 10003;
    public const SIGNATURE_EXPIRED = 10018;
    public const SIGNATURE_MISMATCH = 10035;
    public const FEATURE_NOT_ENTITLED = 10042;

    // Bucket Operations
    public const INVALID_BUCKET_NAME = 10005;
    public const BUCKET_NOT_FOUND = 10006;
    public const BUCKET_NOT_EMPTY = 10008;
    public const BUCKET_LIMIT_EXCEEDED = 10009;
    public const BUCKET_ALREADY_EXISTS = 10073;

    // Object Management
    public const OBJECT_NOT_FOUND = 10007;
    public const INVALID_OBJECT_KEY = 10020;
    public const OBJECT_TOO_LARGE = 100100;
    public const METADATA_TOO_LARGE = 10012;
    public const OBJECT_LOCKED = 10069;

    // Upload & Requests
    public const MISSING_CONTENT_LENGTH = 10033;
    public const INCOMPLETE_BODY = 10013;
    public const INVALID_CHECKSUM_HEADER = 10014;
    public const CHECKSUM_MISMATCH = 10037;
    public const INVALID_RANGE = 10039;
    public const PRECONDITION_FAILED = 10031;

    // Multipart Uploads
    public const PART_TOO_SMALL = 10011;
    public const MULTIPART_NOT_FOUND = 10024;
    public const PARTS_MISSING = 10025;
    public const UNEQUAL_PART_SIZES = 10048;

    // Service Issues
    public const INTERNAL_ERROR = 10001;
    public const SERVICE_UNAVAILABLE = 10043;
    public const CLIENT_DISCONNECTED = 10054;
    public const RATE_LIMIT_EXCEEDED = 10058;

    /**
     * Get exception class for specific error code.
     *
     * @return class-string<R2Exception>
     */
    public static function getExceptionClass(int $code): string
    {
        switch ($code) {
            case self::INVALID_CREDENTIALS:
            case self::ACCESS_DENIED:
            case self::SIGNATURE_EXPIRED:
            case self::SIGNATURE_MISMATCH:
            case self::FEATURE_NOT_ENTITLED:
                return AuthenticationException::class;

            case self::INVALID_BUCKET_NAME:
            case self::BUCKET_NOT_FOUND:
            case self::BUCKET_NOT_EMPTY:
            case self::BUCKET_LIMIT_EXCEEDED:
            case self::BUCKET_ALREADY_EXISTS:
                return BucketException::class;

            case self::OBJECT_NOT_FOUND:
            case self::INVALID_OBJECT_KEY:
            case self::OBJECT_TOO_LARGE:
            case self::METADATA_TOO_LARGE:
            case self::OBJECT_LOCKED:
                return ObjectException::class;

            case self::MISSING_CONTENT_LENGTH:
            case self::INCOMPLETE_BODY:
            case self::INVALID_CHECKSUM_HEADER:
            case self::CHECKSUM_MISMATCH:
            case self::PART_TOO_SMALL:
            case self::MULTIPART_NOT_FOUND:
            case self::PARTS_MISSING:
            case self::UNEQUAL_PART_SIZES:
                return UploadException::class;

            case self::RATE_LIMIT_EXCEEDED:
                return RateLimitException::class;

            default:
                return R2Exception::class;
        }
    }
}
