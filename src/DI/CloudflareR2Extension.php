<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\DI;

use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use NksHub\NetteCloudflareR2\Client\Configuration;
use NksHub\NetteCloudflareR2\Client\R2Client;
use NksHub\NetteCloudflareR2\Client\R2ClientFactory;
use NksHub\NetteCloudflareR2\Storage\StorageClass;
use NksHub\NetteCloudflareR2\Tracy\R2Panel;

/**
 * Nette DI extension for Cloudflare R2 integration.
 *
 * Supports multiple buckets configuration:
 *
 * r2:
 *     accountId: 'xxx'
 *     accessKeyId: 'xxx'
 *     secretAccessKey: 'xxx'
 *     jurisdiction: 'eu'
 *
 *     buckets:
 *         vzkazy:
 *             bucket: 'chatujme-vzkazy-private'
 *             # no publicUrl = private
 *
 *         fotogalerie:
 *             bucket: 'chatujme-fotogalerie'
 *             publicUrl: 'https://pub-xxx.r2.dev'
 *
 *     default: 'vzkazy'  # or first bucket if not specified
 */
class CloudflareR2Extension extends CompilerExtension
{
    public function getConfigSchema(): Schema
    {
        $bucketSchema = Expect::structure([
            'bucket' => Expect::string()->required(),
            'publicUrl' => Expect::string()->nullable(),
            'storageClass' => Expect::anyOf(...StorageClass::getAll())->default(StorageClass::STANDARD),
            'cacheControl' => Expect::string()->nullable(),
        ]);

        return Expect::structure([
            // Credentials (shared for all buckets)
            'accountId' => Expect::string()->required(),
            'accessKeyId' => Expect::string()->required(),
            'secretAccessKey' => Expect::string()->required(),
            'jurisdiction' => Expect::anyOf(null, 'eu', 'fedramp')->nullable(),

            // Named buckets
            'buckets' => Expect::arrayOf($bucketSchema)->default([]),

            // Default bucket (name from buckets array, or first one)
            'default' => Expect::string()->nullable(),

            // Legacy single-bucket config (for backwards compatibility)
            'defaultBucket' => Expect::string()->nullable(),
            'publicUrl' => Expect::string()->nullable(),

            // Upload defaults
            'upload' => Expect::structure([
                'storageClass' => Expect::anyOf(...StorageClass::getAll())->default(StorageClass::STANDARD),
                'cacheControl' => Expect::string()->nullable(),
                'chunkSize' => Expect::int()->default(8388608), // 8MB
                'autoMultipart' => Expect::bool()->default(true),
            ]),

            // Debug
            'tracy' => Expect::bool()->default(false),
            'logging' => Expect::bool()->default(false),
        ]);
    }

    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        $config = $this->config;

        // Determine default bucket
        $defaultBucketName = $this->resolveDefaultBucket($config);

        // Client factory for all buckets
        $builder->addDefinition($this->prefix('clientFactory'))
            ->setFactory(R2ClientFactory::class, [$config])
            ->setAutowired(true);

        // Register named bucket clients
        $firstClient = null;
        foreach ($config->buckets as $name => $bucketConfig) {
            $clientDef = $builder->addDefinition($this->prefix('bucket.' . $name))
                ->setFactory([$this->prefix('@clientFactory'), 'create'], [$name])
                ->setAutowired(false);

            if ($firstClient === null) {
                $firstClient = $clientDef;
            }

            // If this is the default bucket, make it autowired
            if ($name === $defaultBucketName) {
                $clientDef->setAutowired(true);

                // Also register as main client alias
                $builder->addAlias($this->prefix('client'), $this->prefix('bucket.' . $name));
            }
        }

        // Legacy: if no buckets configured but defaultBucket is set
        if (empty($config->buckets) && $config->defaultBucket) {
            $legacyConfig = new Configuration(
                $config->accountId,
                $config->accessKeyId,
                $config->secretAccessKey,
                $config->defaultBucket,
                $config->publicUrl,
                $config->jurisdiction,
                $config->upload->storageClass,
                $config->upload->cacheControl,
                $config->upload->chunkSize,
                $config->upload->autoMultipart
            );

            $builder->addDefinition($this->prefix('client'))
                ->setFactory(R2Client::class, [$legacyConfig])
                ->setAutowired(true);

            $firstClient = $builder->getDefinition($this->prefix('client'));
        }

        // Tracy panel
        if ($config->tracy && $firstClient !== null) {
            $builder->addDefinition($this->prefix('tracy.panel'))
                ->setFactory(R2Panel::class, [$firstClient])
                ->setAutowired(false);
        }
    }

    public function beforeCompile(): void
    {
        $builder = $this->getContainerBuilder();
        $config = $this->config;

        // Register Tracy panel
        if ($config->tracy) {
            $tracyClass = 'Tracy\Bar';
            if (class_exists($tracyClass) && $builder->hasDefinition($this->prefix('tracy.panel'))) {
                foreach ($builder->findByType($tracyClass) as $tracy) {
                    /** @var ServiceDefinition $tracy */
                    $tracy->addSetup('addPanel', ['@' . $this->prefix('tracy.panel')]);
                }
            }
        }
    }

    private function resolveDefaultBucket(object $config): ?string
    {
        // Explicit default
        if ($config->default) {
            return $config->default;
        }

        // First bucket
        if (!empty($config->buckets)) {
            return array_key_first((array) $config->buckets);
        }

        return null;
    }
}
