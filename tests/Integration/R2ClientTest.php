<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Tests\Integration;

use NksHub\NetteCloudflareR2\Client\Configuration;
use NksHub\NetteCloudflareR2\Client\R2Client;
use NksHub\NetteCloudflareR2\Storage\StorageClass;
use NksHub\NetteCloudflareR2\Storage\UploadOptions;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';

/**
 * Integration tests for R2Client.
 *
 * Requires environment variables:
 * - R2_ACCOUNT_ID
 * - R2_ACCESS_KEY_ID
 * - R2_SECRET_ACCESS_KEY
 * - R2_BUCKET
 * - R2_JURISDICTION (optional)
 *
 * @skip
 */
class R2ClientTest extends TestCase
{
    private ?R2Client $client = null;
    private string $testPrefix = 'test-' ;

    protected function setUp(): void
    {
        $accountId = getenv('R2_ACCOUNT_ID');
        $accessKeyId = getenv('R2_ACCESS_KEY_ID');
        $secretAccessKey = getenv('R2_SECRET_ACCESS_KEY');
        $bucket = getenv('R2_BUCKET');
        $jurisdiction = getenv('R2_JURISDICTION') ?: null;

        if (!$accountId || !$accessKeyId || !$secretAccessKey || !$bucket) {
            $this->skip('R2 credentials not configured');
            return;
        }

        $config = new Configuration(
            $accountId,
            $accessKeyId,
            $secretAccessKey,
            $bucket,
            null,
            $jurisdiction
        );

        $this->client = new R2Client($config);
        $this->testPrefix = 'test-' . time() . '-';
    }

    protected function tearDown(): void
    {
        // Cleanup test files
        if ($this->client !== null) {
            try {
                $this->client->deleteByPrefix($this->testPrefix);
            } catch (\Throwable $e) {
                // Ignore cleanup errors
            }
        }
    }

    public function testUploadAndDownload(): void
    {
        $key = $this->testPrefix . 'upload-test.txt';
        $content = 'Hello, R2! ' . uniqid();

        // Upload
        $url = $this->client->upload($key, $content);
        Assert::type('string', $url);
        Assert::contains($key, $url);

        // Download
        $downloaded = $this->client->get($key);
        Assert::same($content, $downloaded);

        // Verify exists
        Assert::true($this->client->exists($key));

        // Delete
        $this->client->delete($key);
        Assert::false($this->client->exists($key));
    }

    public function testMetadata(): void
    {
        $key = $this->testPrefix . 'metadata-test.txt';
        $content = 'Test content';

        $options = UploadOptions::create()
            ->withContentType('text/plain')
            ->withMetadata(['custom-key' => 'custom-value']);

        $this->client->upload($key, $content, $options);

        $metadata = $this->client->getMetadata($key);

        Assert::same($key, $metadata->getKey());
        Assert::same(strlen($content), $metadata->getSize());
        Assert::same('text/plain', $metadata->getContentType());
        Assert::contains('custom-value', $metadata->getCustomMetadataValue('custom-key') ?? '');

        $this->client->delete($key);
    }

    public function testListing(): void
    {
        // Upload multiple files
        for ($i = 1; $i <= 5; $i++) {
            $this->client->upload($this->testPrefix . "list-{$i}.txt", "Content {$i}");
        }

        // List
        $result = $this->client->list($this->testPrefix . 'list-');
        Assert::count(5, $result['objects']);

        // Count
        $count = $this->client->count($this->testPrefix . 'list-');
        Assert::same(5, $count);
    }

    public function testCopy(): void
    {
        $sourceKey = $this->testPrefix . 'copy-source.txt';
        $destKey = $this->testPrefix . 'copy-dest.txt';
        $content = 'Copy me!';

        $this->client->upload($sourceKey, $content);

        $url = $this->client->copy($sourceKey, $destKey);
        Assert::type('string', $url);

        $copied = $this->client->get($destKey);
        Assert::same($content, $copied);
    }

    public function testPresignedUrl(): void
    {
        $key = $this->testPrefix . 'presigned-test.txt';
        $content = 'Presigned content';

        $this->client->upload($key, $content);

        $presignedUrl = $this->client->getPresignedUrl($key, 60);

        Assert::false($presignedUrl->isExpired());
        Assert::type('string', $presignedUrl->getUrl());
        Assert::same('GET', $presignedUrl->getMethod());
        Assert::same($key, $presignedUrl->getKey());

        // Verify URL is accessible
        $downloaded = @file_get_contents($presignedUrl->getUrl());
        Assert::same($content, $downloaded);
    }

    public function testStorageClass(): void
    {
        $key = $this->testPrefix . 'storage-class-test.txt';

        $options = UploadOptions::create()
            ->withStorageClass(StorageClass::INFREQUENT_ACCESS);

        $this->client->upload($key, 'Infrequent access content', $options);

        $metadata = $this->client->getMetadata($key);
        Assert::same(StorageClass::INFREQUENT_ACCESS, $metadata->getStorageClass());
    }

    public function testDeleteMultiple(): void
    {
        // Upload files
        for ($i = 1; $i <= 3; $i++) {
            $this->client->upload($this->testPrefix . "batch-{$i}.txt", "Batch {$i}");
        }

        // Delete all
        $keys = [
            $this->testPrefix . 'batch-1.txt',
            $this->testPrefix . 'batch-2.txt',
            $this->testPrefix . 'batch-3.txt',
        ];

        $errors = $this->client->deleteMultiple($keys);
        Assert::same([], $errors);

        // Verify deleted
        foreach ($keys as $key) {
            Assert::false($this->client->exists($key));
        }
    }

    public function testStats(): void
    {
        $this->client->resetStats();

        $key = $this->testPrefix . 'stats-test.txt';
        $content = 'Stats content';

        $this->client->upload($key, $content);
        $this->client->get($key);
        $this->client->delete($key);

        $stats = $this->client->getStats();

        Assert::true($stats['operations'] >= 3);
        Assert::same(1, $stats['uploads']);
        Assert::same(1, $stats['downloads']);
        Assert::same(1, $stats['deletes']);
        Assert::true($stats['bytesUploaded'] > 0);
        Assert::true($stats['bytesDownloaded'] > 0);
    }
}

(new R2ClientTest())->run();
