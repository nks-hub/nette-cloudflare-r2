<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Download;

use NksHub\NetteCloudflareR2\Client\R2Client;

/**
 * Helper class for file downloads.
 */
class FileDownloader
{
    private R2Client $client;

    public function __construct(R2Client $client)
    {
        $this->client = $client;
    }

    /**
     * Download to a local file.
     *
     * @param string $key Object key
     * @param string $destination Local destination path
     */
    public function download(string $key, string $destination): void
    {
        $this->client->download($key, $destination);
    }

    /**
     * Download to a temporary file.
     *
     * @param string $key Object key
     * @param string $prefix Temp file prefix
     * @return string Path to temporary file
     */
    public function downloadToTemp(string $key, string $prefix = 'r2_'): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), $prefix);
        if ($tempFile === false) {
            throw new \RuntimeException('Cannot create temporary file');
        }

        $this->client->download($key, $tempFile);

        return $tempFile;
    }

    /**
     * Download and return content.
     *
     * @param string $key Object key
     * @return string Content
     */
    public function getContent(string $key): string
    {
        return $this->client->get($key);
    }

    /**
     * Download with byte range (partial download).
     *
     * @param string $key Object key
     * @param int $start Start byte
     * @param int|null $end End byte (null = to end of file)
     * @return string Content
     */
    public function getRange(string $key, int $start, ?int $end = null): string
    {
        $range = $end !== null ? "bytes={$start}-{$end}" : "bytes={$start}-";

        $s3 = $this->client->getS3Client();
        $result = $s3->getObject([
            'Bucket' => $this->client->getConfiguration()->getDefaultBucket(),
            'Key' => $key,
            'Range' => $range,
        ]);

        return (string) $result['Body'];
    }
}
