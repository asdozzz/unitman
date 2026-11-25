<?php

declare(strict_types=1);

namespace App\AiChat\Business\Port;

use App\AiChat\Business\Model\FiltrSessiyAiChata;
use App\AiChat\Business\Model\SessiyaAiChata;
use App\AiChat\Business\ReadModel\SpisokSessiyAiChataReadModel;

/**
 * Хранилище сессий AI-чата.
 *
 * Порт работает только с агрегатом `SessiyaAiChata` и его read-model, не
 * раскрывая деталей БД. История сообщений живёт в отдельном порту
 * {@see SoobshenieAiChataRepository}.
 */
interface SessiyaAiChataRepository
{
    public function naytiPoId(string $id): ?SessiyaAiChata;

    /**
     * Загрузить сессию с блокировкой строки (`select ... for update`).
     *
     * Вызывать разрешено только внутри открытой транзакции: блокировка живёт
     * до её завершения. Нужна для согласованного изменения сессии и истории.
     */
    public function naytiPoIdDlyaObnovleniya(string $id): ?SessiyaAiChata;

    /**
     * Вставить новую или обновить существующую сессию (upsert).
     */
    public function sohranit(SessiyaAiChata $sessiya): void;

    /**
     * Страница списка сессий аккаунта с превью первого сообщения.
     *
     * Возвращаются только сессии с хотя бы одним сообщением. Сортировка —
     * `updated_at desc`, пагинация берётся из фильтра. Поиск ищет по заголовку
     * и тексту первого сообщения. В результат входят метаданные пагинации
     * (`limit`, `offset`, `total`, `hasMore`).
     */
    public function spisok(string $accountId, FiltrSessiyAiChata $filtr): SpisokSessiyAiChataReadModel;

    /**
     * Массово архивировать истёкшие сессии аккаунта с причиной `expired`.
     *
     * @return int количество архивированных записей
     */
    public function arhivirovatIstekchie(string $accountId, int $now): int;

    /**
     * Удалить сессию вместе со всей её историей в одной транзакции.
     */
    public function udalit(SessiyaAiChata $sessiya): void;
}
