<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Url;

use NksHub\NetteCloudflareR2\Client\Configuration;

/**
 * URL generator for public access to R2 objects.
 */
class UrlGenerator
{
    private Configuration $configuration;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Get public URL for an object.
     *
     * Uses custom domain if configured, otherwise falls back to S3 endpoint.
     *
     * @param string $key Object key
     * @return string Public URL
     */
    public function getPublicUrl(string $key): string
    {
        $publicUrl = $this->configuration->getPublicUrl();

        if ($publicUrl !== null) {
            return rtrim($publicUrl, '/') . '/' . ltrim($key, '/');
        }

        // Fallback to S3 endpoint (requires public bucket or presigned URL)
        return $this->getS3Url($key);
    }

    /**
     * Get S3-compatible URL for an object.
     *
     * @param string $key Object key
     * @return string S3 URL
     */
    public function getS3Url(string $key): string
    {
        $endpoint = $this->configuration->getEndpoint();
        $bucket = $this->configuration->getDefaultBucket();

        return "{$endpoint}/{$bucket}/" . ltrim($key, '/');
    }

    /**
     * Get r2.dev URL (if enabled on bucket).
     *
     * @param string $key Object key
     * @param string $subdomain Bucket's r2.dev subdomain
     * @return string r2.dev URL
     */
    public function getR2DevUrl(string $key, string $subdomain): string
    {
        return "https://{$subdomain}.r2.dev/" . ltrim($key, '/');
    }

    /**
     * Build a complete URL with query parameters.
     *
     * @param string $baseUrl Base URL
     * @param array<string, string> $params Query parameters
     * @return string Complete URL
     */
    public function buildUrl(string $baseUrl, array $params = []): string
    {
        if (empty($params)) {
            return $baseUrl;
        }

        $query = http_build_query($params);
        $separator = strpos($baseUrl, '?') !== false ? '&' : '?';

        return $baseUrl . $separator . $query;
    }
}
