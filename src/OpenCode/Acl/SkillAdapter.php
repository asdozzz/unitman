<?php

declare(strict_types=1);

namespace App\OpenCode\Acl;

use App\OpenCode\Business\Port\UmeetPoluchatNavyki;
use App\Skill\Api\SkillApi;

/**
 * Антикоррупционный слой над модулем Skill.
 *
 * OpenCode не знает о доменной модели навыков: получает уже собранный
 * эффективный набор через публичный фасад.
 */
final class SkillAdapter implements UmeetPoluchatNavyki
{
    public function __construct(private readonly SkillApi $skillApi)
    {
    }

    public function effektivnyeNavykiDlyaProektov(array $projectIds): array
    {
        return $this->skillApi->effektivnyeNavykiDlyaProektov($projectIds)->toArray();
    }
}
