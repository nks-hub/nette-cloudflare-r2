# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.2] - 2026-11-06

### Fixed

- `R2Client::convertException()` always returned the generic `R2Exception` with error code `0`.
  It now maps the S3 error code (and, failing that, the HTTP status) to the R2 numeric code, so
  `ObjectException`, `BucketException`, `AuthenticationException`, `UploadException` and
  `RateLimitException` are thrown as documented and `getR2ErrorCode()` returns e.g. `10007`.
  `RateLimitException::getRetryAfter()` reads the `Retry-After` header.
- `R2Client::getStats()` now counts uploads made through `ChunkedUploader`, including failed ones,
  and the bytes of auto-multipart uploads over 100 MB.
- PHPStan (level 6) passes: the ignore pattern for `Cannot access property` now matches the
  `array<string, mixed>|object` wording of current PHPStan 1.x.
- `composer.phar` is no longer tracked and is listed in `.gitignore`.
- Release dates of 1.0.0 and 1.1.0 now match the git tags.

### Added

- Unit tests for exception mapping and statistics (mocked S3 handler, no network).

## [1.1.1] - 2026-07-17

### Fixed

- `R2Client::convertException()` crashed with a `TypeError` when the underlying
  `S3Exception` carried no HTTP response (e.g. cURL/SSL connection failures),
  masking the original error. Null status codes now default to `0`.

## [1.1.0] - 2026-03-22

### Fixed

- SSRF protection and safe unserialize
- Hardcoded R2 credentials in tests replaced with environment variables
- Updated PHP version constraint

## [1.0.0] - 2026-01-24

### Added

- Initial release
- Full S3-compatible API support for Cloudflare R2
- Nette DI extension with comprehensive configuration
- R2Client with fluent API for all operations
- Support for multiple buckets via R2ClientFactory
- Upload operations:
  - String and stream upload
  - Nette FileUpload integration
  - Automatic multipart upload for large files
  - Chunked upload with progress tracking
- Download operations:
  - Content download
  - Stream download
  - File download with byte ranges
- Presigned URL generation (GET, PUT, DELETE, HEAD)
- Object operations:
  - Copy, exists, metadata
  - Batch delete
  - Delete by prefix
- Storage class support (STANDARD, STANDARD_IA)
- Lifecycle rules management
- Bucket operations (list, create, delete, CORS)
- Cloudflare API client for extended features:
  - Custom domains management
  - r2.dev domain control
  - Event notifications
  - Account metrics
  - Temporary credentials
- Tracy debugger panel
- Comprehensive exception hierarchy with R2 error codes
- Full documentation and examples

### Security

- Secure credential handling
- Support for jurisdictional restrictions (EU, FedRAMP)

[1.0.0]: https://github.com/nks-hub/nette-cloudflare-r2/releases/tag/v1.0.0
