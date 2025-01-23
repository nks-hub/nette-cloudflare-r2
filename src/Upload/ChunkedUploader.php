<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Upload;

use NksHub\NetteCloudflareR2\Api\MultipartOperations;
use NksHub\NetteCloudflareR2\Client\R2Client;
use NksHub\NetteCloudflareR2\Storage\UploadOptions;

/**
 * Chunked uploader for very large files with progress tracking.
 */
class ChunkedUploader
{
    private R2Client $client;
    private MultipartOperations $multipart;

    /** @var callable|null */
    private $progressCallback;

    public function __construct(R2Client $client)
    {
        $this->client = $client;
        $this->multipart = $client->multipart();
    }

    /**
     * Set progress callback.
     *
     * @param callable(int $uploaded, int $total): void $callback
     * @return self
     */
    public function onProgress(callable $callback): self
    {
        $this->progressCallback = $callback;
        return $this;
    }

    /**
     * Upload a large file in chunks.
     *
     * @param string $key Object key
     * @param string $filePath Local file path
     * @param UploadOptions|null $options Upload options
     * @param int|null $chunkSize Chunk size in bytes (default 8MB)
     * @return string Public URL
     */
    public function upload(
        string $key,
        string $filePath,
        ?UploadOptions $options = null,
        ?int $chunkSize = null
    ): string {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: $filePath");
        }

        $fileSize = filesize($filePath);
        if ($fileSize === false) {
            throw new \RuntimeException("Cannot get file size: $filePath");
        }

        $chunkSize = $chunkSize ?? ($options?->getChunkSize() ?? MultipartOperations::DEFAULT_PART_SIZE);
        $chunkSize = max($chunkSize, MultipartOperations::MIN_PART_SIZE);

        // For small files, use regular upload
        if ($fileSize <= $chunkSize) {
            return $this->client->uploadFromPath($key, $filePath, $options);
        }

        // Start multipart upload
        $uploadId = $this->multipart->create($key, $options);

        try {
            $parts = [];
            $partNumber = 1;
            $uploaded = 0;

            $handle = fopen($filePath, 'rb');
            if ($handle === false) {
                throw new \RuntimeException("Cannot open file: $filePath");
            }

            try {
                while (!feof($handle)) {
                    $chunk = fread($handle, $chunkSize);
                    if ($chunk === false || $chunk === '') {
                        break;
                    }

                    $parts[] = $this->multipart->uploadPart($key, $uploadId, $partNumber, $chunk);
                    $uploaded += strlen($chunk);
                    $partNumber++;

                    if ($this->progressCallback !== null) {
                        ($this->progressCallback)($uploaded, $fileSize);
                    }
                }
            } finally {
                fclose($handle);
            }

            $this->multipart->complete($key, $uploadId, $parts);

            return $this->client->getPublicUrl($key);
        } catch (\Throwable $e) {
            $this->multipart->abort($key, $uploadId);
            throw $e;
        }
    }

    /**
     * Resume an interrupted upload.
     *
     * @param string $key Object key
     * @param string $uploadId Upload ID
     * @param string $filePath Local file path
     * @param int|null $chunkSize Chunk size
     * @return string Public URL
     */
    public function resume(
        string $key,
        string $uploadId,
        string $filePath,
        ?int $chunkSize = null
    ): string {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: $filePath");
        }

        $fileSize = filesize($filePath);
        if ($fileSize === false) {
            throw new \RuntimeException("Cannot get file size: $filePath");
        }

        $chunkSize = $chunkSize ?? MultipartOperations::DEFAULT_PART_SIZE;

        // Get already uploaded parts
        $existingParts = $this->multipart->listParts($key, $uploadId);
        $parts = [];
        $uploadedBytes = 0;

        foreach ($existingParts as $part) {
            $parts[] = [
                'ETag' => $part['ETag'],
                'PartNumber' => $part['PartNumber'],
            ];
            $uploadedBytes += $part['Size'];
        }

        $startPartNumber = count($parts) + 1;

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: $filePath");
        }

        try {
            // Skip already uploaded bytes
            if ($uploadedBytes > 0) {
                fseek($handle, $uploadedBytes);
            }

            $partNumber = $startPartNumber;
            $uploaded = $uploadedBytes;

            while (!feof($handle)) {
                $chunk = fread($handle, max(1, $chunkSize));
                if ($chunk === false || $chunk === '') {
                    break;
                }

                $parts[] = $this->multipart->uploadPart($key, $uploadId, $partNumber, $chunk);
                $uploaded += strlen($chunk);
                $partNumber++;

                if ($this->progressCallback !== null) {
                    ($this->progressCallback)($uploaded, $fileSize);
                }
            }
        } finally {
            fclose($handle);
        }

        $this->multipart->complete($key, $uploadId, $parts);

        return $this->client->getPublicUrl($key);
    }
}
