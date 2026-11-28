<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

/**
 * Доступ OpenCode к эффективному набору навыков проекта.
 *
 * Реализация — Acl-адаптер над публичным `SkillApi`. Проекты передаёт
 * вызывающий код, уже проверив доступ к сессии; клиентский ввод сюда
 * не попадает.
 */
interface UmeetPoluchatNavyki
{
    /**
     * @param list<string> $projectIds
     *
     * @return array{
     *     skills: list<array<string, mixed>>,
     *     manifestHash: string,
     *     totalBytes: int,
     *     truncated: bool
     * }
     */
    public function effektivnyeNavykiDlyaProektov(array $projectIds): array;
}
