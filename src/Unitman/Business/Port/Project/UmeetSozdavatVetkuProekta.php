<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\Model\Repo;

interface UmeetSozdavatVetkuProekta
{
    /**
     * Создаёт ветку в хранилище от указанной базовой ветки.
     *
     * @throws \DomainException project.branch_exists когда ветка уже существует
     * @throws \DomainException project.branch_bez_prav когда у токена нет прав
     */
    public function sozdatVetkuVHranilishe(
        Repo $repo,
        string $projectCode,
        string $nazvanieVetki,
        string $otVetki,
        string $token,
    ): void;
}
