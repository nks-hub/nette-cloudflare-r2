<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Tests\Unit\Client;

use NksHub\NetteCloudflareR2\Client\Configuration;
use NksHub\NetteCloudflareR2\Storage\StorageClass;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../../bootstrap.php';

class ConfigurationTest extends TestCase
{
    public function testBasicConfiguration(): void
    {
        $config = new Configuration(
            'account123',
            'accessKey',
            'secretKey',
            'my-bucket'
        );

        Assert::same('account123', $config->getAccountId());
        Assert::same('accessKey', $config->getAccessKeyId());
        Assert::same('secretKey', $config->getSecretAccessKey());
        Assert::same('my-bucket', $config->getDefaultBucket());
        Assert::null($config->getPublicUrl());
        Assert::null($config->getJurisdiction());
        Assert::same(StorageClass::STANDARD, $config->getDefaultStorageClass());
    }

    public function testEndpointGeneration(): void
    {
        $config = new Configuration(
            'account123',
            'accessKey',
            'secretKey',
            'my-bucket'
        );

        Assert::same(
            'https://account123.r2.cloudflarestorage.com',
            $config->getEndpoint()
        );
    }

    public function testJurisdictionalEndpoint(): void
    {
        $config = new Configuration(
            'account123',
            'accessKey',
            'secretKey',
            'my-bucket',
            null,
            'eu'
        );

        Assert::same(
            'https://account123.eu.r2.cloudflarestorage.com',
            $config->getEndpoint()
        );
    }

    public function testFullConfiguration(): void
    {
        $config = new Configuration(
            'account123',
            'accessKey',
            'secretKey',
            'my-bucket',
            'https://cdn.example.com',
            'eu',
            StorageClass::INFREQUENT_ACCESS,
            'max-age=31536000',
            16777216,
            false
        );

        Assert::same('https://cdn.example.com', $config->getPublicUrl());
        Assert::same('eu', $config->getJurisdiction());
        Assert::same(StorageClass::INFREQUENT_ACCESS, $config->getDefaultStorageClass());
        Assert::same('max-age=31536000', $config->getDefaultCacheControl());
        Assert::same(16777216, $config->getChunkSize());
        Assert::false($config->isAutoMultipart());
    }
}

(new ConfigurationTest())->run();
