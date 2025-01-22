<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Api;

use NksHub\NetteCloudflareR2\Exception\R2Exception;

/**
 * Cloudflare API client for R2-specific operations.
 *
 * These operations are not available through the S3-compatible API
 * and require the Cloudflare API token.
 *
 * @see https://developers.cloudflare.com/api/resources/r2/
 */
class CloudflareApi
{
    private const API_BASE = 'https://api.cloudflare.com/client/v4';

    private string $accountId;
    private string $apiToken;

    public function __construct(string $accountId, string $apiToken)
    {
        $this->accountId = $accountId;
        $this->apiToken = $apiToken;
    }

    // =========================================================================
    // BUCKET MANAGEMENT
    // =========================================================================

    /**
     * List all buckets in the account.
     *
     * @return array<array<string, mixed>>
     */
    public function listBuckets(): array
    {
        $response = $this->request('GET', "/accounts/{$this->accountId}/r2/buckets");
        return $response['buckets'] ?? [];
    }

    /**
     * Get bucket details.
     *
     * @param string $bucketName Bucket name
     * @return array<string, mixed>
     */
    public function getBucket(string $bucketName): array
    {
        return $this->request('GET', "/accounts/{$this->accountId}/r2/buckets/{$bucketName}");
    }

    /**
     * Create a new bucket.
     *
     * @param string $name Bucket name
     * @param string|null $locationHint Location hint
     * @param string|null $storageClass Default storage class
     * @return array<string, mixed>
     */
    public function createBucket(
        string $name,
        ?string $locationHint = null,
        ?string $storageClass = null
    ): array {
        $data = ['name' => $name];

        if ($locationHint !== null) {
            $data['locationHint'] = $locationHint;
        }

        if ($storageClass !== null) {
            $data['storageClass'] = $storageClass;
        }

        return $this->request('POST', "/accounts/{$this->accountId}/r2/buckets", $data);
    }

    /**
     * Delete a bucket.
     *
     * @param string $bucketName Bucket name
     * @return array<string, mixed>
     */
    public function deleteBucket(string $bucketName): array
    {
        return $this->request('DELETE', "/accounts/{$this->accountId}/r2/buckets/{$bucketName}");
    }

    // =========================================================================
    // CUSTOM DOMAINS
    // =========================================================================

    /**
     * List custom domains for a bucket.
     *
     * @param string $bucketName Bucket name
     * @return array<array<string, mixed>>
     */
    public function listCustomDomains(string $bucketName): array
    {
        $response = $this->request(
            'GET',
            "/accounts/{$this->accountId}/r2/buckets/{$bucketName}/domains/custom"
        );
        return $response['domains'] ?? [];
    }

    /**
     * Attach a custom domain to a bucket.
     *
     * @param string $bucketName Bucket name
     * @param string $domain Domain name
     * @return array<string, mixed>
     */
    public function attachCustomDomain(string $bucketName, string $domain): array
    {
        return $this->request(
            'POST',
            "/accounts/{$this->accountId}/r2/buckets/{$bucketName}/domains/custom",
            ['domain' => $domain]
        );
    }

    /**
     * Remove a custom domain from a bucket.
     *
     * @param string $bucketName Bucket name
     * @param string $domain Domain name
     * @return array<string, mixed>
     */
    public function removeCustomDomain(string $bucketName, string $domain): array
    {
        return $this->request(
            'DELETE',
            "/accounts/{$this->accountId}/r2/buckets/{$bucketName}/domains/custom/{$domain}"
        );
    }

    // =========================================================================
    // MANAGED (r2.dev) DOMAIN
    // =========================================================================

    /**
     * Get r2.dev domain state.
     *
     * @param string $bucketName Bucket name
     * @return array<string, mixed>
     */
    public function getManagedDomain(string $bucketName): array
    {
        return $this->request(
            'GET',
            "/accounts/{$this->accountId}/r2/buckets/{$bucketName}/domains/managed"
        );
    }

    /**
     * Enable/disable r2.dev public access.
     *
     * @param string $bucketName Bucket name
     * @param bool $enabled Whether to enable public access
     * @return array<string, mixed>
     */
    public function setManagedDomain(string $bucketName, bool $enabled): array
    {
        return $this->request(
            'PUT',
            "/accounts/{$this->accountId}/r2/buckets/{$bucketName}/domains/managed",
            ['enabled' => $enabled]
        );
    }

    // =========================================================================
    // EVENT NOTIFICATIONS
    // =========================================================================

    /**
     * List event notification rules.
     *
     * @param string $bucketName Bucket name
     * @return array<array<string, mixed>>
     */
    public function listEventNotifications(string $bucketName): array
    {
        $response = $this->request(
            'GET',
            "/accounts/{$this->accountId}/event_notifications/r2/{$bucketName}/configuration"
        );
        return $response['rules'] ?? [];
    }

    /**
     * Create event notification rule.
     *
     * @param string $bucketName Bucket name
     * @param string $queueId Queue ID
     * @param array<string> $eventTypes Event types (object-create, object-delete)
     * @param string|null $prefix Object key prefix filter
     * @param string|null $suffix Object key suffix filter
     * @return array<string, mixed>
     */
    public function createEventNotification(
        string $bucketName,
        string $queueId,
        array $eventTypes,
        ?string $prefix = null,
        ?string $suffix = null
    ): array {
        $data = [
            'rules' => [
                [
                    'actions' => $eventTypes,
                ],
            ],
        ];

        if ($prefix !== null) {
            $data['rules'][0]['prefix'] = $prefix;
        }

        if ($suffix !== null) {
            $data['rules'][0]['suffix'] = $suffix;
        }

        return $this->request(
            'PUT',
            "/accounts/{$this->accountId}/event_notifications/r2/{$bucketName}/configuration/queues/{$queueId}",
            $data
        );
    }

    /**
     * Delete event notification rule(s).
     *
     * @param string $bucketName Bucket name
     * @param string $queueId Queue ID
     * @return array<string, mixed>
     */
    public function deleteEventNotification(string $bucketName, string $queueId): array
    {
        return $this->request(
            'DELETE',
            "/accounts/{$this->accountId}/event_notifications/r2/{$bucketName}/configuration/queues/{$queueId}"
        );
    }

    // =========================================================================
    // METRICS
    // =========================================================================

    /**
     * Get R2 account metrics.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return $this->request('GET', "/accounts/{$this->accountId}/r2/metrics");
    }

    // =========================================================================
    // TEMPORARY CREDENTIALS
    // =========================================================================

    /**
     * Create temporary access credentials.
     *
     * @param string $bucket Bucket name
     * @param string $permission Permission (admin-read-write, admin-read-only, object-read-write, object-read-only)
     * @param int $ttlSeconds Time to live in seconds
     * @param string|null $prefix Object key prefix restriction
     * @return array{accessKeyId: string, secretAccessKey: string, sessionToken: string}
     */
    public function createTempCredentials(
        string $bucket,
        string $permission = 'object-read-write',
        int $ttlSeconds = 3600,
        ?string $prefix = null
    ): array {
        $data = [
            'bucket' => $bucket,
            'permission' => $permission,
            'ttlSeconds' => $ttlSeconds,
        ];

        if ($prefix !== null) {
            $data['objects'] = $prefix;
        }

        return $this->request(
            'POST',
            "/accounts/{$this->accountId}/r2/temp-access-credentials",
            $data
        );
    }

    // =========================================================================
    // INTERNAL
    // =========================================================================

    /**
     * Make API request.
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array<string, mixed>|null $data Request body
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, ?array $data = null): array
    {
        $url = self::API_BASE . $endpoint;

        $options = [
            'http' => [
                'method' => $method,
                'header' => [
                    'Authorization: Bearer ' . $this->apiToken,
                    'Content-Type: application/json',
                ],
                'ignore_errors' => true,
            ],
        ];

        if ($data !== null) {
            $options['http']['content'] = json_encode($data);
        }

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new R2Exception('Failed to connect to Cloudflare API', 0, 500);
        }

        $decoded = json_decode($response, true);

        if ($decoded === null) {
            throw new R2Exception('Invalid JSON response from Cloudflare API', 0, 500);
        }

        if (isset($decoded['success']) && $decoded['success'] === false) {
            $error = $decoded['errors'][0] ?? ['message' => 'Unknown error', 'code' => 0];
            throw new R2Exception(
                $error['message'] ?? 'Cloudflare API error',
                (int) ($error['code'] ?? 0),
                400
            );
        }

        return $decoded['result'] ?? $decoded;
    }
}
