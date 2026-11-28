<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Support;

use App\OpenCode\Business\AgentMode\ReadModel\RezhimAgentaHoda;
use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\ProgressHodaOpenCode;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Model\WorkspaceKontekst;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;

/**
 * Подмена Runner'а для API-тестов: проверяет контракт порта, не поднимая Temporal.
 *
 * Файл намеренно не оканчивается на `Test.php`, поэтому PHPUnit не собирает
 * его как тест-класс (см. `suffix="Test.php"` в `phpunit.xml.dist`).
 *
 * Логика повторяет реальный адаптер в той части, которая видна вызывающему:
 * `nachatHod` возвращает переданный `workflowId`, а `kanalProgressa` строит имя
 * канала из него же. Результат и прогресс задаются тестом **после** старта
 * хода — так же, как их позже вернул бы опрос Temporal.
 *
 * Юниты записываются в том порядке, в котором их передал `ZapuskatelHodaOpenCode`:
 * это и есть multi-root workspace, где первый юнит — cwd хода. Поля `unitId`
 * и `unitProjectId` дублируют первый юнит — для читаемости существующих проверок.
 */
final class ImitaciyaVypolneniyaHoda implements UmeetVypolnyatHodOpenCode
{
    /**
     * @var list<array{
     *     workflowId: string,
     *     accountId: string,
     *     credentialId: string,
     *     sessionId: string|null,
     *     unitProjectId: string|null,
     *     unitId: string|null,
     *     units: list<array{unitId: string, unitProjectId: string}>,
     *     prompt: string,
     *     answers: list<array{QuestionId: string, OptionId: string|null, Text: string|null}>,
     *     history: list<array{Role: string, Text: string}>,
     *     workspace: WorkspaceKontekst|null,
     *     readOnly: bool,
     *     token: string|null,
     *     model: string|null,
     *     agentModeId: string|null,
     *     agentModeName: string|null
     * }>
     */
    private array $vyzovy = [];

    /**
     * @var array<string, RezultatHodaOpenCode>
     */
    private array $rezultaty = [];

    /**
     * @var array<string, ProgressHodaOpenCode>
     */
    private array $progress = [];

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
        ?string $model = null,
        ?RezhimAgentaHoda $rezhimAgenta = null,
    ): string {
        /** @var list<PrivyazkaKUnitu> $units */
        /** @var list<array{QuestionId: string, OptionId: string|null, Text: string|null}> $answers */
        /** @var list<array{Role: string, Text: string}> $history */
        $unitsPayload = array_map(
            static fn (PrivyazkaKUnitu $privyazka): array => [
                'unitId' => $privyazka->unitId,
                'unitProjectId' => $privyazka->unitProjectId,
            ],
            $units,
        );

        $pervyiUnit = $unitsPayload[0] ?? null;

        $this->vyzovy[] = [
            'workflowId' => $workflowId,
            'accountId' => $accountId,
            'credentialId' => $credentialId,
            'sessionId' => $sessionId,
            'unitProjectId' => $pervyiUnit['unitProjectId'] ?? null,
            'unitId' => $pervyiUnit['unitId'] ?? null,
            'units' => $unitsPayload,
            'prompt' => $prompt,
            'answers' => $answers,
            'history' => $history,
            'workspace' => $workspace,
            'readOnly' => $readOnly,
            'token' => $token,
            'model' => $model,
            'agentModeId' => $rezhimAgenta?->modeId,
            'agentModeName' => $rezhimAgenta?->modeName,
        ];

        return $workflowId;
    }

    public function kanalProgressa(string $workflowId): string
    {
        return 'opencode.progress.' . $workflowId;
    }

    public function poluchitResultatHoda(string $workflowId): ?RezultatHodaOpenCode
    {
        return $this->rezultaty[$workflowId] ?? null;
    }

    public function poluchitProgressHoda(string $workflowId): ?ProgressHodaOpenCode
    {
        return $this->progress[$workflowId] ?? null;
    }

    /**
     * @return list<array{
     *     workflowId: string,
     *     accountId: string,
     *     credentialId: string,
     *     sessionId: string|null,
     *     unitProjectId: string|null,
     *     unitId: string|null,
     *     units: list<array{unitId: string, unitProjectId: string}>,
     *     prompt: string,
     *     answers: list<array{QuestionId: string, OptionId: string|null, Text: string|null}>,
     *     history: list<array{Role: string, Text: string}>,
     *     workspace: WorkspaceKontekst|null,
     *     readOnly: bool,
     *     token: string|null,
     *     model: string|null
     * }>
     */
    public function vyzovy(): array
    {
        return $this->vyzovy;
    }

    public function kolichestvoVyzovov(): int
    {
        return \count($this->vyzovy);
    }

    /**
     * @return array{
     *     workflowId: string,
     *     accountId: string,
     *     credentialId: string,
     *     sessionId: string|null,
     *     unitProjectId: string|null,
     *     unitId: string|null,
     *     units: list<array{unitId: string, unitProjectId: string}>,
     *     prompt: string,
     *     answers: list<array{QuestionId: string, OptionId: string|null, Text: string|null}>,
     *     history: list<array{Role: string, Text: string}>,
     *     workspace: WorkspaceKontekst|null,
     *     readOnly: bool,
     *     token: string|null,
     *     model: string|null
     * }|null
     */
    public function posledniiVyzov(): ?array
    {
        if ($this->vyzovy === []) {
            return null;
        }

        return $this->vyzovy[\count($this->vyzovy) - 1];
    }

    /**
     * Идентификаторы юнитов последнего хода в порядке привязки (первый — cwd).
     *
     * @return list<string>
     */
    public function unitIdsPoslednegoHoda(): array
    {
        $vyzov = $this->posledniiVyzov();

        if ($vyzov === null) {
            return [];
        }

        return array_map(
            static fn (array $unit): string => $unit['unitId'],
            $vyzov['units'],
        );
    }

    public function posledniiWorkflowId(): ?string
    {
        return $this->posledniiVyzov()['workflowId'] ?? null;
    }

    /**
     * Привязывает результат к последнему запущенному ходу.
     *
     * Ход ещё не сохранён в БД на момент вызова порта, поэтому связать
     * результат с ним можно только через `workflowId`, который бэкенд
     * сгенерировал до старта и передал в `nachatHod`.
     */
    public function zadatRezultatDlyaPoslednegoHoda(RezultatHodaOpenCode $rezultat): void
    {
        $workflowId = $this->posledniiWorkflowId();

        if ($workflowId === null) {
            throw new \LogicException('Ход ещё не запускался: некуда привязать результат');
        }

        $this->rezultaty[$workflowId] = $rezultat;
    }

    public function zadatProgressDlyaPoslednegoHoda(ProgressHodaOpenCode $progress): void
    {
        $workflowId = $this->posledniiWorkflowId();

        if ($workflowId === null) {
            throw new \LogicException('Ход ещё не запускался: некуда привязать прогресс');
        }

        $this->progress[$workflowId] = $progress;
    }
}
