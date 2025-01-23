<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Tests\Unit\Storage;

use NksHub\NetteCloudflareR2\Storage\StorageClass;
use NksHub\NetteCloudflareR2\Storage\UploadOptions;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../../bootstrap.php';

class UploadOptionsTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $options = UploadOptions::create();

        Assert::same(StorageClass::STANDARD, $options->getStorageClass());
        Assert::null($options->getContentType());
        Assert::null($options->getCacheControl());
        Assert::same([], $options->getMetadata());
        Assert::true($options->isAutoMultipart());
        Assert::same(8388608, $options->getChunkSize());
    }

    public function testImmutability(): void
    {
        $options1 = UploadOptions::create();
        $options2 = $options1->withContentType('image/jpeg');

        Assert::notSame($options1, $options2);
        Assert::null($options1->getContentType());
        Assert::same('image/jpeg', $options2->getContentType());
    }

    public function testFluentInterface(): void
    {
        $options = UploadOptions::create()
            ->withStorageClass(StorageClass::INFREQUENT_ACCESS)
            ->withContentType('application/pdf')
            ->withCacheControl('max-age=3600')
            ->withMetadata(['key' => 'value'])
            ->withAutoMultipart(false)
            ->withChunkSize(16777216);

        Assert::same(StorageClass::INFREQUENT_ACCESS, $options->getStorageClass());
        Assert::same('application/pdf', $options->getContentType());
        Assert::same('max-age=3600', $options->getCacheControl());
        Assert::same(['key' => 'value'], $options->getMetadata());
        Assert::false($options->isAutoMultipart());
        Assert::same(16777216, $options->getChunkSize());
    }

    public function testToAwsParams(): void
    {
        $options = UploadOptions::create()
            ->withContentType('image/png')
            ->withCacheControl('max-age=86400')
            ->withMetadata(['author' => 'test']);

        $params = $options->toAwsParams();

        Assert::same('STANDARD', $params['StorageClass']);
        Assert::same('image/png', $params['ContentType']);
        Assert::same('max-age=86400', $params['CacheControl']);
        Assert::same(['author' => 'test'], $params['Metadata']);
    }
}

(new UploadOptionsTest())->run();
