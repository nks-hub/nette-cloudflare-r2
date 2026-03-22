<?php

/**
 * Manual integration test script.
 *
 * Run: php tests/manual-test.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NksHub\NetteCloudflareR2\Client\Configuration;
use NksHub\NetteCloudflareR2\Client\R2Client;
use NksHub\NetteCloudflareR2\Storage\UploadOptions;
use NksHub\NetteCloudflareR2\Api\CloudflareApi;

// Configuration - set via environment variables
$accountId = getenv('R2_ACCOUNT_ID') ?: 'your-account-id';
$accessKeyId = getenv('R2_ACCESS_KEY_ID') ?: 'your-access-key-id';
$secretAccessKey = getenv('R2_SECRET_ACCESS_KEY') ?: 'your-secret-access-key';
$bucket = getenv('R2_BUCKET') ?: 'your-bucket-name';
$jurisdiction = 'eu';

echo "=== Nette Cloudflare R2 Manual Test ===\n\n";

// Create configuration
$config = new Configuration(
    $accountId,
    $accessKeyId,
    $secretAccessKey,
    $bucket,
    null,
    $jurisdiction
);

echo "Endpoint: " . $config->getEndpoint() . "\n";
echo "Bucket: " . $config->getDefaultBucket() . "\n\n";

// Create client
$r2 = new R2Client($config);

// Test prefix for cleanup
$testPrefix = 'test-' . date('Ymd-His') . '/';

try {
    // 1. Test upload
    echo "1. Testing upload...\n";
    $testContent = "Hello R2! Test at " . date('Y-m-d H:i:s');
    $key = $testPrefix . 'hello.txt';
    $url = $r2->upload($key, $testContent);
    echo "   Uploaded: {$url}\n";

    // 2. Test exists
    echo "2. Testing exists...\n";
    $exists = $r2->exists($key);
    echo "   Exists: " . ($exists ? 'YES' : 'NO') . "\n";

    // 3. Test download
    echo "3. Testing download...\n";
    $downloaded = $r2->get($key);
    echo "   Downloaded: " . substr($downloaded, 0, 50) . "...\n";
    echo "   Content match: " . ($downloaded === $testContent ? 'YES' : 'NO') . "\n";

    // 4. Test metadata
    echo "4. Testing metadata...\n";
    $metadata = $r2->getMetadata($key);
    echo "   Size: " . $metadata->getFormattedSize() . "\n";
    echo "   Content-Type: " . ($metadata->getContentType() ?? 'N/A') . "\n";
    echo "   Storage Class: " . ($metadata->getStorageClass() ?? 'STANDARD') . "\n";

    // 5. Test presigned URL
    echo "5. Testing presigned URL...\n";
    $presigned = $r2->getPresignedUrl($key, 3600);
    echo "   URL: " . substr($presigned->getUrl(), 0, 80) . "...\n";
    echo "   Expires: " . $presigned->getExpiresAt()->format('Y-m-d H:i:s') . "\n";

    // 6. Test copy
    echo "6. Testing copy...\n";
    $copyKey = $testPrefix . 'hello-copy.txt';
    $copyUrl = $r2->copy($key, $copyKey);
    echo "   Copied to: {$copyUrl}\n";

    // 7. Test listing
    echo "7. Testing listing...\n";
    $result = $r2->list($testPrefix);
    echo "   Found " . count($result['objects']) . " objects:\n";
    foreach ($result['objects'] as $obj) {
        echo "   - {$obj->getKey()} ({$obj->getFormattedSize()})\n";
    }

    // 8. Test with options
    echo "8. Testing upload with options...\n";
    $imageKey = $testPrefix . 'test-image.txt';
    $options = UploadOptions::create()
        ->withContentType('text/plain')
        ->withCacheControl('max-age=3600')
        ->withMetadata(['test-key' => 'test-value']);
    $r2->upload($imageKey, 'Image placeholder content', $options);
    $imageMeta = $r2->getMetadata($imageKey);
    echo "   Uploaded with Content-Type: " . ($imageMeta->getContentType() ?? 'N/A') . "\n";

    // 9. Test stats
    echo "9. Getting stats...\n";
    $stats = $r2->getStats();
    echo "   Operations: {$stats['operations']}\n";
    echo "   Uploads: {$stats['uploads']}\n";
    echo "   Downloads: {$stats['downloads']}\n";
    echo "   Bytes uploaded: {$stats['bytesUploaded']}\n";
    echo "   Bytes downloaded: {$stats['bytesDownloaded']}\n";

    // 10. Cleanup
    echo "10. Cleaning up...\n";
    $deleted = $r2->deleteByPrefix($testPrefix);
    echo "   Deleted {$deleted} objects\n";

    echo "\n=== All tests passed! ===\n";

} catch (\Throwable $e) {
    echo "\n!!! ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";

    // Try cleanup anyway
    try {
        echo "\nAttempting cleanup...\n";
        $r2->deleteByPrefix($testPrefix);
    } catch (\Throwable $e2) {
        // Ignore
    }

    exit(1);
}
