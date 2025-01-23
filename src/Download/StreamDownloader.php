<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Download;

use Nette\Application\Responses\FileResponse;
use Nette\Http\IResponse;
use NksHub\NetteCloudflareR2\Client\R2Client;

/**
 * Stream-based downloader for efficient file delivery.
 */
class StreamDownloader
{
    private R2Client $client;

    public function __construct(R2Client $client)
    {
        $this->client = $client;
    }

    /**
     * Get a stream resource for the object.
     *
     * @param string $key Object key
     * @return resource Stream resource
     */
    public function getStream(string $key)
    {
        return $this->client->getStream($key);
    }

    /**
     * Stream directly to output (for large files).
     *
     * @param string $key Object key
     * @param IResponse|null $response Nette HTTP response (for headers)
     * @param string|null $filename Download filename
     * @param bool $attachment Force download instead of inline display
     */
    public function streamToOutput(
        string $key,
        ?IResponse $response = null,
        ?string $filename = null,
        bool $attachment = true
    ): void {
        $metadata = $this->client->getMetadata($key);

        if ($response !== null) {
            $response->setContentType($metadata->getContentType() ?? 'application/octet-stream');

            $disposition = $attachment ? 'attachment' : 'inline';
            $downloadName = $filename ?? basename($key);
            $response->setHeader('Content-Disposition', "{$disposition}; filename=\"{$downloadName}\"");
            $response->setHeader('Content-Length', (string) $metadata->getSize());
        }

        $stream = $this->client->getStream($key);

        while (!feof($stream)) {
            echo fread($stream, 8192);
            flush();
        }

        fclose($stream);
    }

    /**
     * Create Nette FileResponse from R2 object.
     *
     * Downloads to temp file first (required for FileResponse).
     *
     * @param string $key Object key
     * @param string|null $filename Download filename
     * @param string|null $contentType Content type
     * @param bool $forceDownload Force download
     * @return FileResponse
     */
    public function createFileResponse(
        string $key,
        ?string $filename = null,
        ?string $contentType = null,
        bool $forceDownload = true
    ): FileResponse {
        $tempFile = tempnam(sys_get_temp_dir(), 'r2_');
        if ($tempFile === false) {
            throw new \RuntimeException('Cannot create temporary file');
        }

        $this->client->download($key, $tempFile);

        $filename = $filename ?? basename($key);
        $contentType = $contentType ?? $this->client->getMetadata($key)->getContentType();

        return new FileResponse($tempFile, $filename, $contentType, $forceDownload);
    }

    /**
     * Stream with callback for progress tracking.
     *
     * @param string $key Object key
     * @param callable(string $chunk, int $totalRead): void $callback
     * @return int Total bytes read
     */
    public function streamWithCallback(string $key, callable $callback): int
    {
        $stream = $this->client->getStream($key);
        $totalRead = 0;

        while (!feof($stream)) {
            $chunk = fread($stream, 8192);
            if ($chunk === false) {
                break;
            }
            $totalRead += strlen($chunk);
            $callback($chunk, $totalRead);
        }

        fclose($stream);

        return $totalRead;
    }
}
