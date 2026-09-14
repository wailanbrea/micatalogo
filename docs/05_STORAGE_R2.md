# Cloudflare R2 Storage

Final media uses Cloudflare R2 Standard through Laravel Filesystem with `league/flysystem-aws-s3-v3`.
Production uses a custom media domain, never `r2.dev`.

## Storage Disks

1. **`r2`**: S3-compatible driver targeting Cloudflare R2 endpoints with content-versioned paths.
   - Endpoint: `https://<account_id>.r2.cloudflarestorage.com`
   - Custom URL: `https://media.micatalogo.com` (or user-defined media domain).
   - Visibility: `public`.
   - Error handling: `throw => true`.

2. **`temp`**: Local private disk under `storage/app/private/temp` for incoming user uploads before queue processing.

## Service Layer: MediaStorageService

- Implements automatic fallback to local `public` disk when R2 credentials are not set in `.env`, allowing local development and offline test execution.
- Enforces strict rejection of `r2.dev` domains in production environments.
- Generates content-versioned object keys:
  - Products: `products/{publicId}/{variant}-{checksum_prefix}.webp`
  - Shop Logos: `shops/{publicId}/logo-{checksum_prefix}.webp`
- Exposes storage `healthCheck()` verifying read/write/delete capabilities.
