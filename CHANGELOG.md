# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-23

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
