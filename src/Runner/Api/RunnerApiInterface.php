<?php

namespace App\Runner\Api;

use App\Runner\Business\Command\IzmenitRezhimRedaktoraUnita;
use App\Runner\Business\Command\NachatDeistvieUnita;
use App\Runner\Business\Command\NachatHodOpenCode;
use App\Runner\Business\Command\NachatObnovlenieUnita;
use App\Runner\Business\Command\NachatOstanovkuUnita;
use App\Runner\Business\Command\NachatPodgotovkuUnita;
use App\Runner\Business\Command\NachatSborkuUnita;
use App\Runner\Business\Command\NachatSbrosPodgotovkiUnita;
use App\Runner\Business\Command\NachatUdalenieUnita;
use App\Runner\Business\Command\NachatZapuskUnita;
use App\Runner\Business\Command\ProveritKonteinerUnita;
use App\Runner\Business\Command\PodgotovitSessiyuRedaktora;
use App\Runner\Business\Command\RemoveProjectCommand;
use App\Runner\Business\Command\Workspace\ObnovitWorkspaceZapros;
use App\Runner\Business\Command\Workspace\OstanovitWorkspace;
use App\Runner\Business\Command\Workspace\ProveritIzmeneniyaWorkspace;
use App\Runner\Business\Command\Workspace\ResultatObnovleniyaWorkspace;
use App\Runner\Business\Command\Workspace\ResultatOstanovkiWorkspace;
use App\Runner\Business\Command\Workspace\ResultatProverkiIzmeneniyWorkspace;
use App\Runner\Business\Command\Workspace\ResultatUdaleniyaWorkspace;
use App\Runner\Business\Command\Workspace\ResultatZapuskaWorkspace;
use App\Runner\Business\Command\Workspace\UdalitWorkspace;
use App\Runner\Business\Command\Workspace\ZapustitWorkspaceZapros;
use App\Runner\Business\Model\GolangRunner\OpenCode\ResultatHodaOpenCode;
use App\Runner\Business\Model\GolangRunner\Project\RemoveProjectResult;
use App\Runner\Business\Model\GolangRunner\Project\ResultatOchistkiProekta;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatDeistviyaUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatObnovleniyaUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatOstanovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatPodgotovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatProverkiKonteineraUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrokiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrosaPodgotovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatUdaleniyaUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatZapuskaUnita;
use App\Runner\Business\Model\RunnerProgress;

interface RunnerApiInterface
{
    /**
     * Регистрация токена прогресса перед стартом workflow.
     *
     * Пока токен не зарегистрирован, `saveSteps` по нему отклоняется —
     * это защищает KV от записи по произвольным ключам.
     */
    public function zaregistrirovatProgressToken(string $workflowId): void;

    /**
     * Текущий снапшот прогресса задачи (pull-канал, дублирует push).
     *
     * Возвращает null, если раннер ещё не присылал шагов или снапшот истёк.
     */
    public function poluchitProgressZadachi(string $workflowId): ?RunnerProgress;

    /**
     * Имя Centrifugo-канала, в который публикуется прогресс задачи.
     *
     * Вычисляется из `workflowId`, поэтому клиент может подписаться сразу после
     * старта, не дожидаясь первого `saveSteps`. Знание имени канала наружу
     * отдаёт только Runner: префикс — его внутренняя деталь.
     */
    public function poluchitKanalProgressa(string $workflowId): string;

    public function removeProject(RemoveProjectCommand $command): string;
    public function poluchitResultatUdaleniyaProekta(string $workflowId): ?RemoveProjectResult;
    public function poluchitResultatOchistkiProekta(string $workflowId): ?ResultatOchistkiProekta;
    public function nachatSborkuUnita(string $workflowId, NachatSborkuUnita $command): string;
    public function poluchitResultatSborki(string $workflowId): ?ResultatSbrokiUnita;
    public function nachatUdalenieUnita(string $workflowId, NachatUdalenieUnita $command): string;
    public function poluchitResultatUdaleniyaUnita(string $workflowId): ?ResultatUdaleniyaUnita;

    /**
     * Пересоздание IDE-сайдкара уже подготовленного юнита с новым режимом.
     */
    public function izmenitRezhimRedaktoraUnita(string $workflowId, IzmenitRezhimRedaktoraUnita $command): string;

    /**
     * Подготовка редакторской сессии юнита под личным токеном пользователя.
     */
    public function podgotovitSessiyuRedaktora(string $workflowId, PodgotovitSessiyuRedaktora $command): string;

    public function nachatPodgotovkuUnita(string $workflowId, NachatPodgotovkuUnita $command): string;
    public function nachatObnovlenieUnita(string $workflowId, NachatObnovlenieUnita $command): string;
    public function nachatSbrosPodgotovkiUnita(string $workflowId, NachatSbrosPodgotovkiUnita $command): string;
    public function nachatZapuskUnita(string $workflowId, NachatZapuskUnita $command): string;
    public function nachatOstanovkuUnita(string $workflowId, NachatOstanovkuUnita $command): string;
    public function nachatDeistvieUnita(string $workflowId, NachatDeistvieUnita $command): string;
    public function poluchitResultatPodgotovki(string $workflowId): ?ResultatPodgotovkiUnita;
    public function poluchitResultatObnovleniyaUnita(string $workflowId): ?ResultatObnovleniyaUnita;
    public function poluchitResultatSbrosaPodgotovkiUnita(string $workflowId): ?ResultatSbrosaPodgotovkiUnita;
    public function poluchitResultatZapuskaUnita(string $workflowId): ?ResultatZapuskaUnita;
    public function poluchitResultatOstanovkiUnita(string $workflowId): ?ResultatOstanovkiUnita;

    public function poluchitResultatDeistviya(string $workflowId): ?ResultatDeistviyaUnita;

    public function proveritKonteinerUnita(ProveritKonteinerUnita $command): ?ResultatProverkiKonteineraUnita;

    /**
     * Запуск workspace-стенда.
     *
     * Публичный запрос **не содержит токен**: `StorageUrl` для каждого проекта
     * дозапрашивается у Unitman через ACL-порт внутри Runner, после чего
     * обогащённая команда уходит в workflow.
     *
     * @return string id workflow запуска
     */
    public function zapustitWorkspace(string $workflowId, ZapustitWorkspaceZapros $command): string;

    /**
     * Результат запуска workspace; null — workflow ещё не завершён.
     */
    public function poluchitResultatZapuskaWorkspace(string $workflowId): ?ResultatZapuskaWorkspace;

    /**
     * Обновление работающего workspace: новые проекты и/или файлы.
     *
     * @return string id workflow обновления
     */
    public function obnovitWorkspace(string $workflowId, ObnovitWorkspaceZapros $command): string;

    /**
     * Результат обновления workspace; null — workflow ещё не завершён.
     */
    public function poluchitRezultatObnovleniyaWorkspace(string $workflowId): ?ResultatObnovleniyaWorkspace;

    /**
     * Остановка workspace-стенда.
     *
     * @return string id workflow остановки
     */
    public function ostanovitWorkspace(string $workflowId, OstanovitWorkspace $command): string;

    /**
     * Результат остановки workspace; null — workflow ещё не завершён.
     */
    public function poluchitResultatOstanovkiWorkspace(string $workflowId): ?ResultatOstanovkiWorkspace;

    /**
     * Удаление workspace-стенда.
     *
     * @return string id workflow удаления
     */
    public function udalitWorkspace(string $workflowId, UdalitWorkspace $command): string;

    /**
     * Результат удаления workspace; null — workflow ещё не завершён.
     */
    public function poluchitResultatUdaleniyaWorkspace(string $workflowId): ?ResultatUdaleniyaWorkspace;

    /**
     * Синхронная проверка незакоммиченных изменений в проектах workspace.
     */
    public function proveritIzmeneniyaWorkspace(
        ProveritIzmeneniyaWorkspace $command,
    ): ResultatProverkiIzmeneniyWorkspace;

    /**
     * Старт одного хода диалога с OpenCode.
     *
     * Реквизиты подключения Runner подставляет сам — через `Acl`-адаптер над Account.
     * Токен прогресса регистрируется до старта, поэтому `saveSteps` работает без изменений.
     *
     * Команда несёт упорядоченный список юнитов сессии (`Units`): первый юнит —
     * рабочий каталог хода, остальные — дополнительные корни workspace (multi-root).
     * `ProjectId`/`UnitId` дублируют первый юнит для обратной совместимости.
     *
     * @return string id workflow хода
     * @throws \DomainException если реквизиты не найдены или принадлежат другому аккаунту
     */
    public function nachatHodOpenCode(string $workflowId, NachatHodOpenCode $command): string;

    /**
     * Результат хода: ответ, вопрос с вариантами или ошибка.
     *
     * null — workflow ещё не завершён, результата пока нет.
     */
    public function poluchitResultatHodaOpenCode(string $workflowId): ?ResultatHodaOpenCode;
}
