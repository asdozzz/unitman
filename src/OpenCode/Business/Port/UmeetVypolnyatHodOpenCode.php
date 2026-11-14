<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\Model\ProgressHodaOpenCode;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;

/**
 * Исполнение хода диалога на стороне Runner.
 *
 * Один ход = один Temporal workflow: старт -> опрос результата по 5 секунд.
 * Долгих «висящих» workflow нет, поэтому ответ на вопрос пользователя
 * запускает новый ход, а не сигналит в существующий.
 */
interface UmeetVypolnyatHodOpenCode
{
    /**
     * @param list<array{QuestionId: string, OptionId: string|null, Text: string|null}> $answers
     * @param list<array{Role: string, Text: string}> $history
     *
     * @return string идентификатор workflow хода
     *
     * @throws \DomainException если реквизиты не найдены или принадлежат другому аккаунту
     */
    public function nachatHod(
        string $workflowId,
        string $accountId,
        string $credentialId,
        ?string $sessionId,
        string $prompt,
        array $answers,
        array $history,
    ): string;

    /**
     * Имя Centrifugo-канала, в который раннер публикует шаги хода.
     *
     * Имя вычисляется из `workflowId`, поэтому клиент может подписаться сразу
     * после старта хода, не дожидаясь первого `saveSteps`. Формирует имя
     * только Runner — OpenCode его не разбирает и не конструирует.
     */
    public function kanalProgressa(string $workflowId): string;

    /**
     * null — ход ещё выполняется, результата пока нет.
     */
    public function poluchitResultatHoda(string $workflowId): ?RezultatHodaOpenCode;

    /**
     * Снапшот шагов, которые раннер уже прислал через `saveSteps`.
     */
    public function poluchitProgressHoda(string $workflowId): ?ProgressHodaOpenCode;
}