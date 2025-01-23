<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Upload;

use Nette\Http\FileUpload;
use NksHub\NetteCloudflareR2\Client\R2Client;
use NksHub\NetteCloudflareR2\Storage\UploadOptions;

/**
 * Helper class for file uploads with additional features.
 */
class FileUploader
{
    private R2Client $client;
    private string $defaultDirectory;
    private ?UploadOptions $defaultOptions;

    public function __construct(
        R2Client $client,
        string $defaultDirectory = '',
        ?UploadOptions $defaultOptions = null
    ) {
        $this->client = $client;
        $this->defaultDirectory = $defaultDirectory;
        $this->defaultOptions = $defaultOptions;
    }

    /**
     * Upload a Nette FileUpload.
     *
     * @param FileUpload $file Uploaded file
     * @param string|null $directory Target directory (overrides default)
     * @param string|null $filename Custom filename (auto-generated if null)
     * @param UploadOptions|null $options Upload options
     * @return string Public URL
     */
    public function upload(
        FileUpload $file,
        ?string $directory = null,
        ?string $filename = null,
        ?UploadOptions $options = null
    ): string {
        $directory = $directory ?? $this->defaultDirectory;
        $options = $options ?? $this->defaultOptions;

        if ($filename === null) {
            $filename = $this->generateFilename($file);
        }

        $key = $this->buildKey($directory, $filename);

        $options = $options ?? UploadOptions::create();
        if ($file->getContentType() !== null) {
            $options = $options->withContentType($file->getContentType());
        }

        $stream = fopen($file->getTemporaryFile(), 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Cannot open uploaded file');
        }

        try {
            return $this->client->upload($key, $stream, $options);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Upload an image with optional resizing.
     *
     * @param FileUpload $file Uploaded image
     * @param string|null $directory Target directory
     * @param int|null $maxWidth Maximum width (null = no resize)
     * @param int|null $maxHeight Maximum height (null = no resize)
     * @param int $quality JPEG quality (1-100)
     * @return string Public URL
     */
    public function uploadImage(
        FileUpload $file,
        ?string $directory = null,
        ?int $maxWidth = null,
        ?int $maxHeight = null,
        int $quality = 85
    ): string {
        if (!$file->isImage()) {
            throw new \InvalidArgumentException('File is not an image');
        }

        $image = $file->toImage();

        if ($maxWidth !== null || $maxHeight !== null) {
            $image->resize($maxWidth, $maxHeight);
        }

        // Save to temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'r2_');
        $image->save($tempFile, $quality);

        try {
            $directory = $directory ?? $this->defaultDirectory;
            $filename = $this->generateFilename($file);
            $key = $this->buildKey($directory, $filename);

            $options = UploadOptions::create()->withContentType($file->getContentType() ?? 'image/jpeg');

            return $this->client->uploadFromPath($key, $tempFile, $options);
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Upload multiple files.
     *
     * @param FileUpload[] $files Uploaded files
     * @param string|null $directory Target directory
     * @return array<string, string> Filename => URL mapping
     */
    public function uploadMultiple(array $files, ?string $directory = null): array
    {
        $urls = [];

        foreach ($files as $name => $file) {
            if ($file->isOk()) {
                $urls[$name] = $this->upload($file, $directory);
            }
        }

        return $urls;
    }

    /**
     * Generate unique filename.
     *
     * @param FileUpload $file Uploaded file
     * @return string Generated filename
     */
    private function generateFilename(FileUpload $file): string
    {
        $ext = pathinfo($file->getSanitizedName(), PATHINFO_EXTENSION);
        return uniqid('', true) . '_' . time() . '.' . $ext;
    }

    /**
     * Build object key from directory and filename.
     *
     * @param string $directory Directory
     * @param string $filename Filename
     * @return string Object key
     */
    private function buildKey(string $directory, string $filename): string
    {
        $directory = trim($directory, '/');

        if ($directory === '') {
            return $filename;
        }

        return $directory . '/' . $filename;
    }
}
