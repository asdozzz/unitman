<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Repo;

use App\Unitman\Business\Model\Repo;

/**
 * Проверка, что произвольный токен хранилища валиден для указанного Repo.
 */
interface UmeetProveryatTokenHranilisha
{
    public function proveritToken(string $token, Repo $repo): bool;
}
