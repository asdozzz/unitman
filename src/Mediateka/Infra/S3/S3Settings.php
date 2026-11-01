<?php

declare(strict_types=1);

namespace App\Mediateka\Infra\S3;

/**
 * Настройки S3-совместимого хранилища (MinIO).
 */
final class S3Settings
{
    public function __construct(
        public readonly string $endpoint,
        public readonly string $publicEndpoint,
        public readonly string $bucket,
        public readonly string $region,
        public readonly string $accessKey,
        public readonly string $secretKey,
        public readonly bool $usePathStyle,
        public readonly int $presignTtl
    ) {
    }
}
