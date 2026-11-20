<?php

declare(strict_types=1);

namespace App\Runner\Business\Port;

/**
 * Контракт бизнес-слоя Runner: получить URL клонирования проекта с токеном.
 *
 * Токен не покидает Runner: публичный запрос в Runner его не содержит,
 * `RunnerApi` дозапрашивает URL через этот порт перед стартом workflow.
 *
 * Реализация лежит в `Acl` (адаптер над `UnitmanApi`), потому что модуль
 * Runner не имеет права обращаться к внутренним классам Unitman напрямую.
 */
interface UmeetPoluchatUrlKlonaProekta
{
    /**
     * Токенизированный URL клонирования проекта.
     *
     * @throws \DomainException если проект не найден
     */
    public function poluchitUrlKlona(string $projectId): string;
}
