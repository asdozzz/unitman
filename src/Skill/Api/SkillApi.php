<?php

declare(strict_types=1);

namespace App\Skill\Api;

use App\Skill\Business\ReadModel\RezultatEffektivnyhNavykov;
use App\Skill\Business\Utils\PostroenieEffektivnyhNavykov;

/**
 * Публичный фасад модуля Skill для других модулей (OpenCode, Runner).
 *
 * Авторизацию вызывающий код выполняет сам: OpenCode — через проверку доступа
 * к сессии, Runner — через HMAC-подпись раннера. Здесь остаётся только сборка
 * эффективного набора.
 */
final class SkillApi
{
    public function __construct(private readonly PostroenieEffektivnyhNavykov $postroenie)
    {
    }

    /**
     * @param list<string> $projectIds
     */
    public function effektivnyeNavykiDlyaProektov(
        array $projectIds,
        int $maxSkills = PostroenieEffektivnyhNavykov::MAX_SKILLS,
        int $maxTotalBytes = PostroenieEffektivnyhNavykov::MAX_TOTAL_BYTES,
    ): RezultatEffektivnyhNavykov {
        return $this->postroenie->postroit($projectIds, $maxSkills, $maxTotalBytes);
    }
}
