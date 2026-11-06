<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Tests\Unit\Client;

use Aws\Command;
use Aws\Result;
use Aws\MockHandler;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Response;
use NksHub\NetteCloudflareR2\Client\Configuration;
use NksHub\NetteCloudflareR2\Client\R2Client;
use NksHub\NetteCloudflareR2\Exception\AuthenticationException;
use NksHub\NetteCloudflareR2\Exception\BucketException;
use NksHub\NetteCloudflareR2\Exception\ErrorCode;
use NksHub\NetteCloudflareR2\Exception\ObjectException;
use NksHub\NetteCloudflareR2\Exception\R2Exception;
use NksHub\NetteCloudflareR2\Exception\RateLimitException;
use NksHub\NetteCloudflareR2\Exception\UploadException;
use NksHub\NetteCloudflareR2\Upload\ChunkedUploader;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../../bootstrap.php';

class R2ClientTest extends TestCase
{
    /** @param list<Result|\Throwable> $queue */
    private function client(array $queue): R2Client
    {
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => 'https://example.invalid',
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => 'k', 'secret' => 's'],
            'retries' => 0,
            'handler' => new MockHandler($queue),
        ]);

        return new R2Client(new Configuration('acc', 'k', 's', 'bucket'), $s3);
    }

    private function s3Error(string $awsCode, int $status, array $headers = []): S3Exception
    {
        return new S3Exception('error', new Command('GetObject'), [
            'code' => $awsCode,
            'response' => new Response($status, $headers),
        ]);
    }

    /** @return array<string, array{string, int, class-string<R2Exception>, int}> */
    public static function errorProvider(): array
    {
        return [
            'missing key' => ['NoSuchKey', 404, ObjectException::class, 10007],
            'missing bucket' => ['NoSuchBucket', 404, BucketException::class, 10006],
            'access denied' => ['AccessDenied', 403, AuthenticationException::class, 10003],
            'bad signature' => ['SignatureDoesNotMatch', 403, AuthenticationException::class, 10035],
            'missing upload' => ['NoSuchUpload', 404, UploadException::class, 10024],
            'rate limit' => ['SlowDown', 429, RateLimitException::class, 10058],
            'unknown code, 429' => ['Whatever', 429, RateLimitException::class, 10058],
            'unknown code, 403' => ['Whatever', 403, AuthenticationException::class, 10003],
            'unknown' => ['Whatever', 500, R2Exception::class, 0],
        ];
    }

    public function testExceptionsAreMappedToSpecificClasses(): void
    {
        foreach (self::errorProvider() as $label => [$awsCode, $status, $class, $r2Code]) {
            $client = $this->client([$this->s3Error($awsCode, $status)]);
            try {
                $client->get('x');
                Assert::fail("exception expected: $label");
            } catch (R2Exception $e) {
            }
            Assert::same($class, $e::class, $label);
            Assert::same($r2Code, $e->getR2ErrorCode(), $label);
            Assert::same($status, $e->getHttpStatusCode(), $label);
            Assert::type(S3Exception::class, $e->getPrevious(), $label);
        }
    }

    public function testRateLimitReadsRetryAfter(): void
    {
        $client = $this->client([$this->s3Error('SlowDown', 429, ['Retry-After' => '7'])]);
        $e = Assert::exception(fn() => $client->get('x'), RateLimitException::class);
        Assert::same(7, $e->getRetryAfter());
    }

    public function testConnectionFailureWithoutResponse(): void
    {
        $client = $this->client([new S3Exception('connection failed', new Command('GetObject'))]);
        $e = Assert::exception(fn() => $client->get('x'), R2Exception::class);
        Assert::same(R2Exception::class, $e::class);
        Assert::same(0, $e->getHttpStatusCode());
    }

    public function testErrorCodeLookup(): void
    {
        Assert::same(10007, ErrorCode::fromS3Error('NoSuchKey'));
        Assert::same(0, ErrorCode::fromS3Error(null, 500));
        Assert::same(10058, ErrorCode::fromS3Error(null, 429));
    }

    public function testStatsCountChunkedUploads(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'r2');
        file_put_contents($file, str_repeat('a', 5 * 1024 * 1024 + 10));

        try {
            $client = $this->client([
                new Result(['UploadId' => 'u1']),
                new Result(['ETag' => '"e1"']),
                new Result(['ETag' => '"e2"']),
                new Result([]),
            ]);
            (new ChunkedUploader($client))->upload('big.bin', $file, null, 5 * 1024 * 1024);

            $stats = $client->getStats();
            Assert::same(1, $stats['uploads']);
            Assert::same(1, $stats['operations']);
            Assert::same(5 * 1024 * 1024 + 10, $stats['bytesUploaded']);
            Assert::same(0, $stats['errors']);

            $failing = $this->client([
                new Result(['UploadId' => 'u2']),
                $this->s3Error('InternalError', 500),
                new Result([]),
            ]);
            Assert::exception(
                fn() => (new ChunkedUploader($failing))->upload('big.bin', $file, null, 5 * 1024 * 1024),
                S3Exception::class,
            );
            Assert::same(1, $failing->getStats()['errors']);
            Assert::same(0, $failing->getStats()['bytesUploaded']);
        } finally {
            unlink($file);
        }
    }
}

(new R2ClientTest())->run();
