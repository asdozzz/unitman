<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Utils;

use App\OpenCode\Business\AgentMode\ReadModel\RezhimAgentaHoda;
use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\IstochnikRekvizitovOpenCode;
use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\WorkspaceKontekst;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetBronirovatUniti;
use App\OpenCode\Business\Port\UmeetPoluchatTokenRedaktirovaniyaUnita;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;

/**
 * Единая точка старта хода: новый промпт и ответ на вопрос запускаются одинаково.
 *
 * Порядок действий принципиален. Сначала вызывается раннер, и только после
 * успешного старта workflow ход и сессия сохраняются. Иначе отказ в доступе к
 * реквизитам или недоступность Temporal оставили бы в БД ход со статусом
 * RUNNING, который никогда не завершится, и сессия зависла бы навсегда.
 *
 * История собирается до сохранения нового хода: раннер получает контекст
 * предыдущих реплик без текущего промпта — текущий приходит отдельным полем.
 *
 * Юниты захватываются ДО старта workflow: пока ход выполняется, активити
 * Unitman над юнитами не запускаются. Если раннер не принял ход, захват
 * освобождается — иначе юнит остался бы занятым до истечения TTL.
 *
 * Сессия резервируется тем же способом и до старта раннера. Резервация — это
 * единственный атомарный барьер против двух параллельных ходов: проверка
 * «последний ход не выполняется» в use case не защищена от гонки. После
 * успешного старта резервация остаётся до терминального статуса хода.
 */
final class ZapuskatelHodaOpenCode
{
    public function __construct(
        private HodOpenCodeRepository $turnRepository,
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetVypolnyatHodOpenCode $runner,
        private UuidGenerator $uuidGenerator,
        private UmeetBronirovatUniti $bronirovanie,
        private UmeetUpravlyatUnitami $unitami,
        private UmeetPoluchatTokenRedaktirovaniyaUnita $tokeny,
        private OpredelitelRezhimaHodaOpenCode $rezhimHoda,
        private OpredelitelModeliHodaOpenCode $modeli,
        private UmeetUpravlyatProektamiWorkspace $projectami,
    ) {
    }

    /**
     * @param list<array{QuestionId: string, OptionId: string|null, Text: string|null}> $answers
     * @param list<PrivyazkaKUnitu> $units живые юниты, прошедшие гейт готовности; первый — cwd хода
     */
    public function zapustit(
        SessiyaOpenCode $session,
        string $prompt,
        array $answers,
        array $units,
        ?string $model = null,
        ?IstochnikRekvizitovOpenCode $istochnik = null,
        ?RezhimAgentaHoda $rezhimAgenta = null,
    ): HodOpenCode {
        $now = time();

        // Без явного источника — легаси-поведение: реквизиты владельца сессии.
        $accountId = $istochnik?->accountId ?? $session->getAccountId();
        $credentialId = $istochnik?->credentialId
            ?? $rezhimAgenta?->credentialId
            ?? $session->getCredentialId();
        $modelPoUmolchaniyu = $model
            ?? $rezhimAgenta?->model
            ?? $istochnik?->modelPoUmolchaniyu
            ?? $session->getModel();

        // Доступ к проектам проверяется на каждом ходе, а не только при
        // приглашении: права в Unitman могли отозвать после добавления в
        // workspace. Для unit-сессий проверка не нужна — юнит уже ограничен.
        $this->trebovatDostupKProektam($session, $accountId);

        $razreshennayaModel = $this->modeli->opredelit(
            $accountId,
            $credentialId,
            $model,
            $modelPoUmolchaniyu,
        );

        $turn = HodOpenCode::create(
            $this->uuidGenerator->makeGuid(),
            $session->getId(),
            $this->turnRepository->sleduyushiiSeq($session->getId()),
            $this->uuidGenerator->makeGuid(),
            $prompt,
            $answers,
            $now,
            $razreshennayaModel,
            authorAccountId: $accountId,
            credentialId: $credentialId,
            agentModeId: $rezhimAgenta?->modeId,
            agentModeName: $rezhimAgenta?->modeName,
        );

        $history = IstoriyaDialogaOpenCode::sobrat(
            $this->turnRepository->findBySessionId($session->getId()),
        );

        $workspaceKontekst = null;

        if ($session->estWorkspace()) {
            // У workspace нет юнитов: раннер получает сам workspace и проекты.
            $workspace = $session->getWorkspace();
            $units = [];
            $workspaceKontekst = new WorkspaceKontekst(
                $session->getId(),
                $workspace?->isReadOnly() ?? false,
                $workspace?->getProjects() ?? [],
            );
        }

        // Режим вычисляется до захвата юнитов: если личный токен для RW-хода
        // не нашёлся, ход отклоняется сразу, не оставив захват висеть до TTL.
        $readOnly = false;
        $token = null;

        if ($units !== []) {
            [$readOnly, $token] = $this->opredelitRezhim($units, $accountId);
        }

        $vladelec = VladelecZanyatostiOpenCode::dlyaHoda($turn->getId());
        $unitIds = $this->sobratIdUnitov($units);
        $zahvacheno = false;
        $sessiyaZanyata = false;

        try {
            // Сначала резервация сессии: она не даёт двум промптам создать два
            // параллельных хода, даже если оба прошли проверку в use case.
            $this->turnRepository->zanyatSessiyu(
                $session->getId(),
                $turn->getId(),
                $now,
                HodOpenCodeRepository::TTL_ZANYATOSTI_SEKUND,
            );
            $sessiyaZanyata = true;

            if ($unitIds !== []) {
                $this->bronirovanie->zahvatit($unitIds, $vladelec);
                $zahvacheno = true;
            }

            $this->runner->nachatHod(
                $turn->getWorkflowId(),
                $accountId,
                $credentialId,
                $session->getExternalSessionId(),
                $units,
                $prompt,
                $answers,
                $history,
                $workspaceKontekst,
                $readOnly,
                $token,
                model: $razreshennayaModel,
                rezhimAgenta: $rezhimAgenta,
            );
        } catch (\Throwable $e) {
            if ($zahvacheno) {
                $this->bronirovanie->osvobodit($unitIds, $vladelec);
            }

            // Отказ захвата юнитов или раннера не должен оставлять сессию
            // занятой до истечения TTL: снимаем только собственную аренду.
            if ($sessiyaZanyata) {
                $this->otpusitSessiyu($session->getId(), $turn->getId());
            }

            throw $e;
        }

        $this->turnRepository->save($turn);

        // Название workspace задано пользователем и не подменяется промптом.
        if (!$session->estWorkspace()) {
            $session->podpisatPoPromptu($prompt, $now);
        }

        $session->otmetitIzmenenie($now);

        $this->sessionRepository->update($session);

        return $turn;
    }

    /**
     * Проверка доступа автора хода ко всем проектам workspace.
     *
     * Владелец проверяется так же, как участник: если он потерял доступ к
     * проекту, ход блокируется. Unit-сессии не проверяются — их доступ уже
     * ограничен привязкой к юниту.
     *
     * @throws \DomainException `opencode.workspace.project_access_revoked`
     */
    private function trebovatDostupKProektam(SessiyaOpenCode $session, string $accountId): void
    {
        if (!$session->estWorkspace()) {
            return;
        }

        $workspace = $session->getWorkspace();

        if ($workspace === null) {
            return;
        }

        $projectIds = array_map(
            static fn ($project): string => $project->projectId,
            $workspace->getProjects(),
        );

        if (!$this->projectami->estDostupKProektam($projectIds, $accountId)) {
            throw new \DomainException('opencode.workspace.project_access_revoked');
        }
    }

    /**
     * Освобождение резервации не должно маскировать исходную ошибку: ход всё
     * равно не стартовал, а аренда истечёт по TTL.
     */
    private function otpusitSessiyu(string $sessionId, string $turnId): void
    {
        try {
            $this->turnRepository->osvoboditSessiyu($sessionId, $turnId);
        } catch (\Throwable) {
            // Основная ошибка важнее: TTL перехватит аренду при следующем старте.
        }
    }

    /**
     * Решение 15.7-а для хода по юниту.
     *
     * Ход RW только если ВСЕ юниты сессии разрешают правку: если хотя бы один
     * юнит read-only, весь multi-root ход read-only и токен не запрашивается.
     * Токен берётся для первого юнита (cwd хода) — именно в нём выполняются
     * commit/push.
     *
     * @param list<PrivyazkaKUnitu> $units
     *
     * @return array{bool, string|null}
     *
     * @throws \DomainException unit.redaktor.net_lichnogo_tokena|unit.redaktor.token_bez_prav_na_proekt
     */
    private function opredelitRezhim(array $units, string $accountId): array
    {
        $pervyiUnit = reset($units);

        foreach ($units as $privyazka) {
            $sostoyanie = $this->unitami->poluchitSostoyanieUnita($privyazka->unitId);

            if ($this->rezhimHoda->readOnly($sostoyanie, $accountId)) {
                return [true, null];
            }
        }

        if (!$pervyiUnit instanceof PrivyazkaKUnitu) {
            return [true, null];
        }

        return [false, $this->tokeny->poluchitTokenDlyRedaktirovaniyaUnita($pervyiUnit->unitId, $accountId)];
    }

    /**
     * @param list<PrivyazkaKUnitu> $units
     * @return list<string>
     */
    private function sobratIdUnitov(array $units): array
    {
        $unitIds = [];

        foreach ($units as $privyazka) {
            $unitIds[] = $privyazka->unitId;
        }

        return $unitIds;
    }
}
