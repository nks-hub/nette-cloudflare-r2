<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Upload;

use NksHub\NetteCloudflareR2\Client\R2Client;
use NksHub\NetteCloudflareR2\Storage\UploadOptions;

/**
 * Stream-based uploader for handling large files and streams.
 */
class StreamUploader
{
    private R2Client $client;

    public function __construct(R2Client $client)
    {
        $this->client = $client;
    }

    /**
     * Upload from a stream.
     *
     * @param string $key Object key
     * @param resource $stream Input stream
     * @param UploadOptions|null $options Upload options
     * @return string Public URL
     */
    public function uploadStream(string $key, $stream, ?UploadOptions $options = null): string
    {
        return $this->client->upload($key, $stream, $options);
    }

    /**
     * Validate URL against SSRF attack vectors.
     *
     * Blocks non-HTTP(S) schemes and private/reserved IP ranges.
     *
     * @throws \InvalidArgumentException on disallowed scheme or private/internal host
     */
    private function validateUrl(string $url): void
    {
        $parsed = parse_url($url);

        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            throw new \InvalidArgumentException('Invalid URL format.');
        }

        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException("URL scheme '{$scheme}' is not allowed. Only http and https are permitted.");
        }

        $host = $parsed['host'];

        // Resolve hostname to IP for private-range checks
        $ip = filter_var($host, FILTER_VALIDATE_IP) !== false ? $host : gethostbyname($host);

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new \InvalidArgumentException("Cannot resolve host '{$host}'.");
        }

        // Block private, loopback, link-local and reserved ranges
        $blockedRanges = [
            // Loopback
            '127.0.0.0/8',
            // Private class A
            '10.0.0.0/8',
            // Private class B
            '172.16.0.0/12',
            // Private class C
            '192.168.0.0/16',
            // Link-local / AWS metadata
            '169.254.0.0/16',
            // IPv6 loopback
            '::1/128',
            // IPv6 link-local
            'fe80::/10',
            // IPv6 unique local
            'fc00::/7',
        ];

        foreach ($blockedRanges as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                throw new \InvalidArgumentException("URL resolves to a private or reserved IP address and is not allowed.");
            }
        }
    }

    /**
     * Check whether an IP address falls within a CIDR range.
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$range, $prefix] = explode('/', $cidr);
        $prefix = (int) $prefix;

        if (str_contains($cidr, ':')) {
            // IPv6
            $ipBin    = inet_pton($ip);
            $rangeBin = inet_pton($range);
            if ($ipBin === false || $rangeBin === false) {
                return false;
            }
            $ipInt    = gmp_import($ipBin);
            $rangeInt = gmp_import($rangeBin);
            $mask     = gmp_sub(gmp_pow(2, 128), gmp_pow(2, 128 - $prefix));
            return gmp_cmp(gmp_and($ipInt, $mask), gmp_and($rangeInt, $mask)) === 0;
        }

        // IPv4
        $ipLong    = ip2long($ip);
        $rangeLong = ip2long($range);
        if ($ipLong === false || $rangeLong === false) {
            return false;
        }
        $maskLong = $prefix === 0 ? 0 : (~0 << (32 - $prefix));
        return ($ipLong & $maskLong) === ($rangeLong & $maskLong);
    }

    /**
     * Upload from a URL (download and upload).
     *
     * @param string $key Object key
     * @param string $url Source URL
     * @param UploadOptions|null $options Upload options
     * @return string Public URL
     * @throws \InvalidArgumentException if the URL fails SSRF validation
     */
    public function uploadFromUrl(string $key, string $url, ?UploadOptions $options = null): string
    {
        $this->validateUrl($url);

        $stream = fopen($url, 'rb');
        if ($stream === false) {
            throw new \RuntimeException("Cannot open URL: $url");
        }

        try {
            // Try to detect content type from URL
            $headers = get_headers($url, true);
            if ($options === null && isset($headers['Content-Type'])) {
                $contentType = is_array($headers['Content-Type'])
                    ? end($headers['Content-Type'])
                    : $headers['Content-Type'];
                $options = UploadOptions::create()->withContentType($contentType);
            }

            return $this->client->upload($key, $stream, $options);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Upload from PHP input stream (useful for PUT requests).
     *
     * @param string $key Object key
     * @param UploadOptions|null $options Upload options
     * @return string Public URL
     */
    public function uploadFromInput(string $key, ?UploadOptions $options = null): string
    {
        $stream = fopen('php://input', 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Cannot open php://input');
        }

        try {
            return $this->client->upload($key, $stream, $options);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Upload base64-encoded content.
     *
     * @param string $key Object key
     * @param string $base64 Base64-encoded content
     * @param UploadOptions|null $options Upload options
     * @return string Public URL
     */
    public function uploadBase64(string $key, string $base64, ?UploadOptions $options = null): string
    {
        // Handle data URI format
        if (preg_match('/^data:([^;]+);base64,(.+)$/', $base64, $matches)) {
            $contentType = $matches[1];
            $base64 = $matches[2];

            if ($options === null) {
                $options = UploadOptions::create()->withContentType($contentType);
            } elseif ($options->getContentType() === null) {
                $options = $options->withContentType($contentType);
            }
        }

        $content = base64_decode($base64, true);
        if ($content === false) {
            throw new \InvalidArgumentException('Invalid base64 content');
        }

        return $this->client->upload($key, $content, $options);
    }
}
