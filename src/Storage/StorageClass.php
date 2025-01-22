<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Storage;

/**
 * R2 Storage classes.
 *
 * STANDARD: Default class for frequently accessed data. No retrieval fees.
 * STANDARD_IA: Infrequent Access - lower storage cost but includes retrieval fees
 *              and 30-day minimum storage duration.
 */
final class StorageClass
{
    public const STANDARD = 'STANDARD';
    public const INFREQUENT_ACCESS = 'STANDARD_IA';

    /** @var string[] */
    private static array $validClasses = [
        self::STANDARD,
        self::INFREQUENT_ACCESS,
    ];

    public static function isValid(string $class): bool
    {
        return in_array($class, self::$validClasses, true);
    }

    /**
     * @return string[]
     */
    public static function getAll(): array
    {
        return self::$validClasses;
    }
}
