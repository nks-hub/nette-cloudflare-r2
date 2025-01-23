<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\DI;

use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use NksHub\NetteCloudflareR2\Client\Configuration;
use NksHub\NetteCloudflareR2\Client\R2Client;
use NksHub\NetteCloudflareR2\Client\R2ClientFactory;
use NksHub\NetteCloudflareR2\Storage\StorageClass;
use NksHub\NetteCloudflareR2\Tracy\R2Panel;

/**
 * Nette DI extension for Cloudflare R2 integration.
 */
class CloudflareR2Extension extends CompilerExtension
{
    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'accountId' => Expect::string()->required(),
            'accessKeyId' => Expect::string()->required(),
            'secretAccessKey' => Expect::string()->required(),
            'defaultBucket' => Expect::string()->required(),
            'publicUrl' => Expect::string()->nullable(),
            'jurisdiction' => Expect::anyOf(null, 'eu', 'fedramp')->nullable(),
            'upload' => Expect::structure([
                'storageClass' => Expect::anyOf(...StorageClass::getAll())->default(StorageClass::STANDARD),
                'cacheControl' => Expect::string()->nullable(),
                'chunkSize' => Expect::int()->default(8388608), // 8MB
                'autoMultipart' => Expect::bool()->default(true),
            ]),
            'buckets' => Expect::arrayOf(
                Expect::structure([
                    'name' => Expect::string()->required(),
                    'publicUrl' => Expect::string()->nullable(),
                    'storageClass' => Expect::anyOf(...StorageClass::getAll())->default(StorageClass::STANDARD),
                ])
            ),
            'tracy' => Expect::bool()->default(false),
            'logging' => Expect::bool()->default(false),
        ]);
    }

    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        $config = $this->config;

        // Main configuration
        $builder->addDefinition($this->prefix('configuration'))
            ->setFactory(Configuration::class . '::fromConfig', [$config])
            ->setAutowired(false);

        // Main R2 client
        $clientDef = $builder->addDefinition($this->prefix('client'))
            ->setFactory(R2Client::class, [
                new Statement('@' . $this->prefix('configuration')),
            ])
            ->setAutowired(true);

        // Client factory for multiple buckets
        $builder->addDefinition($this->prefix('clientFactory'))
            ->setFactory(R2ClientFactory::class, [
                $config,
            ])
            ->setAutowired(true);

        // Register named bucket clients
        if (!empty($config->buckets)) {
            foreach ($config->buckets as $name => $bucketConfig) {
                $builder->addDefinition($this->prefix('bucket.' . $name))
                    ->setFactory('@' . $this->prefix('clientFactory') . '::create', [$name])
                    ->setAutowired(false);
            }
        }

        // Tracy panel
        if ($config->tracy) {
            $builder->addDefinition($this->prefix('tracy.panel'))
                ->setFactory(R2Panel::class, [$clientDef])
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
            if (class_exists($tracyClass)) {
                foreach ($builder->findByType($tracyClass) as $tracy) {
                    /** @var ServiceDefinition $tracy */
                    $tracy->addSetup('addPanel', ['@' . $this->prefix('tracy.panel')]);
                }
            }
        }
    }
}
