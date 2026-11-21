<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\ProgressHodaOpenCode;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Model\WorkspaceKontekst;

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
     * @param list<PrivyazkaKUnitu> $units живые юниты сессии в порядке привязки;
     *                                    первый — рабочий каталог хода (cwd), остальные —
     *                                    дополнительные корни workspace (multi-root)
     * @param list<array{QuestionId: string, OptionId: string|null, Text: string|null}> $answers
     * @param list<array{Role: string, Text: string}> $history
     * @param bool $readOnly ход по юниту, которому запрещена правка файлов
     * @param string|null $token личный токен владельца для RW-хода; для RO всегда null
     *
     * @return string идентификатор workflow хода
     *
     * @throws \DomainException если реквизиты не найдены или принадлежат другому аккаунту
     * @throws \DomainException unit.redaktor.net_lichnogo_tokena|unit.redaktor.token_bez_prav_na_proekt
     *                           если для RW-хода не нашёлся подходящий личный токен
     */
    public function nachatHod(
        string $workflowId,
        string $accountId,
        string $credentialId,
        ?string $sessionId,
        array $units,
        string $prompt,
        array $answers,
        array $history,
        ?WorkspaceKontekst $workspace = null,
        bool $readOnly = false,
        ?string $token = null,
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