<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Bridge\Nette;

use Nette\Caching\IStorage;
use NksHub\NetteCloudflareR2\Client\R2Client;

/**
 * R2-backed cache storage for Nette.
 *
 * Useful for storing large cache items or sharing cache across multiple servers.
 * Not recommended for high-frequency read/write operations due to latency.
 */
class CacheStorage implements IStorage
{
    private R2Client $client;
    private string $prefix;

    public function __construct(R2Client $client, string $prefix = 'cache/')
    {
        $this->client = $client;
        $this->prefix = rtrim($prefix, '/') . '/';
    }

    public function read(string $key): mixed
    {
        try {
            $data = $this->client->get($this->prefix . $this->sanitizeKey($key));
            $decoded = @unserialize($data);

            if ($decoded === false) {
                return null;
            }

            // Check expiration
            if (isset($decoded['expire']) && $decoded['expire'] < time()) {
                $this->remove($key);
                return null;
            }

            return $decoded['data'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function write(string $key, $data, array $dependencies): void
    {
        $expire = null;

        if (isset($dependencies[self::Expire])) {
            $expire = is_int($dependencies[self::Expire])
                ? $dependencies[self::Expire]
                : $dependencies[self::Expire]->getTimestamp();
        }

        $content = serialize([
            'data' => $data,
            'expire' => $expire,
            'created' => time(),
        ]);

        $this->client->upload($this->prefix . $this->sanitizeKey($key), $content);
    }

    public function remove(string $key): void
    {
        try {
            $this->client->delete($this->prefix . $this->sanitizeKey($key));
        } catch (\Throwable $e) {
            // Ignore errors when removing non-existent keys
        }
    }

    public function clean(array $conditions): void
    {
        if (isset($conditions[self::All])) {
            $this->client->deleteByPrefix($this->prefix);
            return;
        }

        // Tag-based cleaning not supported efficiently
        // Would require listing all objects and checking metadata
    }

    public function lock(string $key): void
    {
        // Locking not supported - R2 is eventually consistent
    }

    public function unlock(string $key): void
    {
        // Locking not supported
    }

    /**
     * Sanitize cache key for R2 object key.
     */
    private function sanitizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-\/]/', '_', $key) ?? $key;
    }
}
