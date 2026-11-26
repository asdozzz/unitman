<?php

declare(strict_types=1);

namespace App\Runner\Business\Port;

/**
 * Контракт бизнес-слоя Runner: получить чистый remote URL и repoId проекта.
 *
 * Токен не покидает Runner: публичный запрос в Runner его не содержит,
 * `RunnerApi` дозапрашивает данные через этот порт перед стартом workflow.
 *
 * Реализация лежит в `Acl` (адаптер над `UnitmanApi`), потому что модуль
 * Runner не имеет права обращаться к внутренним классам Unitman напрямую.
 */
interface UmeetPoluchatUrlKlonaProekta
{
    /**
     * Чистый URL клонирования проекта (без userinfo/токена).
     *
     * @throws \DomainException если проект не найден
     */
    public function poluchitUrlKlona(string $projectId): string;

    /**
     * repoId проекта для just-in-time запроса гранта.
     *
     * @throws \DomainException если проект не найден
     */
    public function poluchitRepoIdKlona(string $projectId): string;
}
