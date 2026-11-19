<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Utils;

/**
 * Единое правило формирования ключа объекта в S3-совместимом хранилище.
 *
 * Ключ зависит только от владельца файла и его идентификатора, поэтому
 * вычисляется одинаково и при потоковой загрузке, и при прямой загрузке
 * в MinIO через presigned URL.
 */
final class KlyuchHranilishchaFayla
{
    public static function sdelat(?string $unitId, ?string $projectId, string $fileId): string
    {
        if ($unitId !== null) {
            return sprintf('units/%s/%s.zip', $unitId, $fileId);
        }

        if ($projectId !== null) {
            return sprintf('projects/%s/%s.zip', $projectId, $fileId);
        }

        return sprintf('drafts/%s.zip', $fileId);
    }
}
