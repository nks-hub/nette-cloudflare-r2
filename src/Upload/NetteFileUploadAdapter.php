<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Upload;

use Nette\Http\FileUpload;
use NksHub\NetteCloudflareR2\Client\R2Client;
use NksHub\NetteCloudflareR2\Storage\UploadOptions;

/**
 * Adapter for seamless integration with Nette FileUpload.
 *
 * Provides a drop-in replacement for local file storage.
 */
class NetteFileUploadAdapter
{
    private R2Client $client;
    private string $baseDirectory;
    private ?UploadOptions $defaultOptions;

    /** @var callable|null */
    private $keyGenerator;

    public function __construct(
        R2Client $client,
        string $baseDirectory = '',
        ?UploadOptions $defaultOptions = null
    ) {
        $this->client = $client;
        $this->baseDirectory = trim($baseDirectory, '/');
        $this->defaultOptions = $defaultOptions;
    }

    /**
     * Set custom key generator.
     *
     * @param callable(FileUpload, string): string $generator Function that generates object key
     * @return self
     */
    public function setKeyGenerator(callable $generator): self
    {
        $this->keyGenerator = $generator;
        return $this;
    }

    /**
     * Save uploaded file to R2.
     *
     * @param FileUpload $file Uploaded file
     * @param string $subdirectory Additional subdirectory
     * @return string Public URL
     */
    public function save(FileUpload $file, string $subdirectory = ''): string
    {
        if (!$file->isOk()) {
            throw new \RuntimeException('File upload failed: ' . $this->getUploadErrorMessage($file->getError()));
        }

        $key = $this->generateKey($file, $subdirectory);
        $options = $this->prepareOptions($file);

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
     * Save image with optional processing.
     *
     * @param FileUpload $file Uploaded image
     * @param string $subdirectory Additional subdirectory
     * @param int|null $maxWidth Maximum width
     * @param int|null $maxHeight Maximum height
     * @param int $quality JPEG quality
     * @return string Public URL
     */
    public function saveImage(
        FileUpload $file,
        string $subdirectory = '',
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

        $tempFile = tempnam(sys_get_temp_dir(), 'r2img_');
        $image->save($tempFile, $quality);

        try {
            $key = $this->generateKey($file, $subdirectory);
            $options = $this->prepareOptions($file);

            return $this->client->uploadFromPath($key, $tempFile, $options);
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Save multiple files.
     *
     * @param FileUpload[] $files Uploaded files
     * @param string $subdirectory Additional subdirectory
     * @return array<string, string|null> Original filename => URL (null if failed)
     */
    public function saveMultiple(array $files, string $subdirectory = ''): array
    {
        $results = [];

        foreach ($files as $file) {
            $originalName = $file->getSanitizedName();

            try {
                $results[$originalName] = $file->isOk() ? $this->save($file, $subdirectory) : null;
            } catch (\Throwable $e) {
                $results[$originalName] = null;
            }
        }

        return $results;
    }

    /**
     * Generate object key for file.
     *
     * @param FileUpload $file Uploaded file
     * @param string $subdirectory Additional subdirectory
     * @return string Object key
     */
    private function generateKey(FileUpload $file, string $subdirectory): string
    {
        if ($this->keyGenerator !== null) {
            return ($this->keyGenerator)($file, $subdirectory);
        }

        $ext = pathinfo($file->getSanitizedName(), PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;

        $parts = array_filter([
            $this->baseDirectory,
            trim($subdirectory, '/'),
            $filename,
        ]);

        return implode('/', $parts);
    }

    /**
     * Prepare upload options from file.
     *
     * @param FileUpload $file Uploaded file
     * @return UploadOptions Upload options
     */
    private function prepareOptions(FileUpload $file): UploadOptions
    {
        $options = $this->defaultOptions ?? UploadOptions::create();

        if ($file->getContentType() !== null && $options->getContentType() === null) {
            $options = $options->withContentType($file->getContentType());
        }

        return $options;
    }

    /**
     * Get human-readable error message.
     *
     * @param int $code Error code
     * @return string Error message
     */
    private function getUploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
            default => 'Unknown error',
        };
    }
}
