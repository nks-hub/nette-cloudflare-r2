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
     * Upload from a URL (download and upload).
     *
     * @param string $key Object key
     * @param string $url Source URL
     * @param UploadOptions|null $options Upload options
     * @return string Public URL
     */
    public function uploadFromUrl(string $key, string $url, ?UploadOptions $options = null): string
    {
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
