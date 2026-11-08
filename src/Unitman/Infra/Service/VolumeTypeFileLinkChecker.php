<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Service;

use App\Mediateka\Business\Port\FileLinkChecker;
use App\Unitman\Business\Port\VolumeType\VolumeTypeFileRepository;

/**
 * Сообщает медиатеке, что архив привязан к типу тома в каталоге проекта.
 *
 * Инверсия зависимости: `Mediateka` не знает про `Unitman`, поэтому проверка
 * подключается через порт [`FileLinkChecker`](../../../Mediateka/Business/Port/FileLinkChecker.php).
 */
final class VolumeTypeFileLinkChecker implements FileLinkChecker
{
    public function __construct(private readonly VolumeTypeFileRepository $volumeTypeFileRepository)
    {
    }

    public function esliFaylPrivyazan(string $fileId): bool
    {
        return $this->volumeTypeFileRepository->esliFaylPrivyazanKHOTyaByOdnomuTipu($fileId);
    }
}
