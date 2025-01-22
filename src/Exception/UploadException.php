<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Exception;

/**
 * Exception for upload-related errors including multipart uploads.
 *
 * R2 Error codes:
 * - 10033: Missing Content-Length header
 * - 10013: Request body incomplete relative to declared length
 * - 10014: Checksum header improperly formatted
 * - 10037: Provided checksum doesn't match uploaded content
 * - 10011: Multipart part below 5 MiB minimum (except final part)
 * - 10024: Multipart upload doesn't exist or was aborted
 * - 10025: Part(s) missing when completing upload
 * - 10048: Non-trailing parts must have identical sizes
 */
class UploadException extends R2Exception
{
}
