<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Сессия диалога: связка «пользователь + реквизиты OpenCode + история ходов».
 *
 * Сессия не хранит API-ключ — только идентификатор реквизитов в Account,
 * поэтому удаление реквизитов не компрометирует историю сессии.
 *
 * Сессия может быть привязана к нескольким юнитам Unitman (multi-root): первый
 * юнит задаёт рабочий каталог хода, остальные подключаются к OpenCode как
 * дополнительные корни workspace. Пока хотя бы один живой юнит не прошёл цикл
 * сборки-запуска, ходы запрещены. Если все юниты сессии удалены, сессия уходит
 * в архив: остаётся в списке, но новые ходы недоступны.
 */
final class SessiyaOpenCode
{
    private const TITLE_MAX_LENGTH = 256;

    /** Максимум юнитов на одну сессию: ограничивает размер multi-root workspace. */
    public const MAX_UNITS = 10;

    /** Максимум проектов workspace-сессии. */
    public const MAX_WORKSPACE_PROJECTS = 20;

    private const WORKSPACE_NAME_MAX_LENGTH = 256;

    /** Причина архивации: все юниты сессии удалены (автоматическое обнаружение). */
    public const ARCHIVE_REASON_UNIT_DELETED = 'unit_deleted';

    /** Причина архивации: пользователь архивировал сессию вручную. */
    public const ARCHIVE_REASON_MANUAL = 'manual';

    /**
     * @param list<PrivyazkaKUnitu> $units
     */
    private function __construct(
        private string $id,
        private string $accountId,
        private string $credentialId,
        private string $title,
        private ?string $externalSessionId,
        private array $units,
        private ?int $archivedAt,
        private ?string $archiveReason,
        private int $createdAt,
        private int $updatedAt,
        private TipSessiiOpenCode $tip,
        private ?Workspace $workspace,
        private ?string $model = null,
    ) {
    }

    /**
     * @param list<PrivyazkaKUnitu> $units
     */
    public static function create(
        string $id,
        string $accountId,
        string $credentialId,
        ?string $title,
        array $units,
        int $now,
        ?string $model = null,
    ): self {
        if (trim($accountId) === '') {
            throw new \DomainException('opencode.session.account_required');
        }

        if (trim($credentialId) === '') {
            throw new \DomainException('opencode.session.credential_required');
        }

        return new self(
            $id,
            $accountId,
            $credentialId,
            self::normalizeTitle($title),
            null,
            self::normalizeUnits($units, true),
            null,
            null,
            $now,
            $now,
            TipSessiiOpenCode::UNIT,
            null,
            $model,
        );
    }

    /**
     * @param list<PrivyazkaKUnitu> $units
     */
    public static function restore(
        string $id,
        string $accountId,
        string $credentialId,
        string $title,
        ?string $externalSessionId,
        array $units,
        ?int $archivedAt,
        ?string $archiveReason,
        int $createdAt,
        int $updatedAt,
        ?string $model = null,
    ): self {
        if ($archivedAt !== null && ($archiveReason === null || trim($archiveReason) === '')) {
            throw new \DomainException('opencode.session.archive_reason_required');
        }

        return new self(
            $id,
            $accountId,
            $credentialId,
            $title,
            $externalSessionId,
            self::normalizeUnits($units, false),
            $archivedAt,
            $archiveReason,
            $createdAt,
            $updatedAt,
            TipSessiiOpenCode::UNIT,
            null,
            $model,
        );
    }

    /**
     * Создание workspace-сессии: юнитов нет, название — это title сессии.
     *
     * @param list<ProektWorkspace> $projects
     */
    public static function createWorkspace(
        string $id,
        string $accountId,
        string $credentialId,
        string $name,
        string $branch,
        array $projects,
        string $initialPrompt,
        int $now,
        ?string $model = null,
    ): self {
        if (trim($accountId) === '') {
            throw new \DomainException('opencode.session.account_required');
        }

        if (trim($credentialId) === '') {
            throw new \DomainException('opencode.session.credential_required');
        }

        $name = trim($name);

        if ($name === '') {
            throw new \DomainException('opencode.workspace.name_required');
        }

        if (mb_strlen($name) > self::WORKSPACE_NAME_MAX_LENGTH) {
            throw new \DomainException('opencode.workspace.name_too_long');
        }

        self::proveritKolichestvoProektovWorkspace($projects);

        return new self(
            $id,
            $accountId,
            $credentialId,
            $name,
            null,
            [],
            null,
            null,
            $now,
            $now,
            TipSessiiOpenCode::WORKSPACE,
            Workspace::create($branch, $initialPrompt, $projects, $now),
            $model,
        );
    }

    /**
     * Восстановление workspace-сессии из хранилища без молчаливой замены
     * статуса и операции.
     */
    public static function restoreWorkspace(
        string $id,
        string $accountId,
        string $credentialId,
        string $name,
        ?string $externalSessionId,
        ?int $archivedAt,
        ?string $archiveReason,
        Workspace $workspace,
        int $createdAt,
        int $updatedAt,
        ?string $model = null,
    ): self {
        if ($archivedAt !== null && ($archiveReason === null || trim($archiveReason) === '')) {
            throw new \DomainException('opencode.session.archive_reason_required');
        }

        return new self(
            $id,
            $accountId,
            $credentialId,
            $name,
            $externalSessionId,
            [],
            $archivedAt,
            $archiveReason,
            $createdAt,
            $updatedAt,
            TipSessiiOpenCode::WORKSPACE,
            $workspace,
            $model,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getCredentialId(): string
    {
        return $this->credentialId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getTip(): TipSessiiOpenCode
    {
        return $this->tip;
    }

    public function estWorkspace(): bool
    {
        return $this->tip === TipSessiiOpenCode::WORKSPACE;
    }

    public function getWorkspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function getExternalSessionId(): ?string
    {
        return $this->externalSessionId;
    }

    /**
     * @return list<PrivyazkaKUnitu>
     */
    public function getUnits(): array
    {
        return $this->units;
    }

    /**
     * Первый юнит — рабочий каталог (cwd) хода; остальные идут доп. корнями workspace.
     */
    public function getPervyiUnit(): ?PrivyazkaKUnitu
    {
        return $this->units[0] ?? null;
    }

    /**
     * Идентификатор первого юнита — легаси-доступ для контракта с раннером.
     */
    public function getUnitId(): ?string
    {
        return $this->units[0]->unitId ?? null;
    }

    /**
     * Проект первого юнита — легаси-доступ для контракта с раннером.
     */
    public function getUnitProjectId(): ?string
    {
        return $this->units[0]->unitProjectId ?? null;
    }

    /**
     * @return list<string>
     */
    public function getUnitIds(): array
    {
        return array_map(static fn (PrivyazkaKUnitu $privyazka): string => $privyazka->unitId, $this->units);
    }

    public function getArchivedAt(): ?int
    {
        return $this->archivedAt;
    }

    public function getArchiveReason(): ?string
    {
        return $this->archiveReason;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    /**
     * Модель, выбранная при создании сессии. `null` — взять дефолт реквизитов
     * при следующем ходе.
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    public function prinadlezhit(string $accountId): bool
    {
        return $this->accountId === $accountId;
    }

    public function privezanaKUnitu(): bool
    {
        return $this->units !== [];
    }

    public function privezanaKUnituSId(string $unitId): bool
    {
        foreach ($this->units as $privyazka) {
            if ($privyazka->prinadlezhitKUnitu($unitId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * В одной сессии допускается не более одного юнита на проект: два юнита
     * одного проекта — это одна и та же кодовая база, второй корень workspace
     * только дублировал бы её.
     */
    public function privezanaKProektu(string $unitProjectId): bool
    {
        foreach ($this->units as $privyazka) {
            if ($privyazka->prinadlezhitKProektu($unitProjectId)) {
                return true;
            }
        }

        return false;
    }

    public function estArhiv(): bool
    {
        return $this->archivedAt !== null;
    }

    /**
     * @throws \DomainException если сессия принадлежит другому пользователю
     */
    public function trebovatVladelca(string $accountId): void
    {
        if (!$this->prinadlezhit($accountId)) {
            throw new \DomainException('opencode.session.not_found');
        }
    }

    /**
     * @throws \DomainException если сессия в архиве
     */
    public function trebovatAktivnuyu(): void
    {
        if ($this->estArhiv()) {
            throw new \DomainException('opencode.session.archived');
        }
    }

    /**
     * Привязка сессии к юниту. Привязка идемпотентна: повторный вызов для уже
     * привязанного юнита ничего не меняет (порядок юнитов сохраняется).
     *
     * @throws \DomainException если сессия в архиве, юнит из уже занятого проекта
     *                          или превышен лимит юнитов
     */
    public function privezatKUnitu(string $unitId, string $unitProjectId, int $now): void
    {
        $this->trebovatAktivnuyu();
        $this->privezatKUnitam([PrivyazkaKUnitu::izZnacheniy($unitId, $unitProjectId)], $now);
    }

    /**
     * Привязка списка юнитов за один вызов. Операция атомарна: сначала
     * проверяются все юниты, и только потом список дополняется — частичной
     * привязки при ошибке не остаётся.
     *
     * @param list<PrivyazkaKUnitu> $units
     *
     * @throws \DomainException если сессия в архиве, юнит из уже занятого проекта
     *                          или превышен лимит юнитов
     */
    public function privezatKUnitam(array $units, int $now): void
    {
        $this->trebovatAktivnuyu();

        $zanyatyeUnity = [];
        $zanyatyeProekty = [];

        foreach ($this->units as $privyazka) {
            $zanyatyeUnity[$privyazka->unitId] = true;
            $zanyatyeProekty[$privyazka->unitProjectId] = true;
        }

        $novye = [];

        foreach ($units as $privyazka) {
            if (isset($zanyatyeUnity[$privyazka->unitId])) {
                continue;
            }

            if (isset($zanyatyeProekty[$privyazka->unitProjectId])) {
                throw new \DomainException('opencode.session.unit_project_conflict');
            }

            $zanyatyeUnity[$privyazka->unitId] = true;
            $zanyatyeProekty[$privyazka->unitProjectId] = true;
            $novye[] = $privyazka;
        }

        if ($novye === []) {
            return;
        }

        if (count($this->units) + count($novye) > self::MAX_UNITS) {
            throw new \DomainException('opencode.session.too_many_units');
        }

        foreach ($novye as $privyazka) {
            $this->units[] = $privyazka;
        }

        $this->updatedAt = $now;
    }

    /**
     * Архивация идемпотентна: повторный вызов не перезаписывает время и причину.
     */
    public function arhivirovat(string $reason, int $now): void
    {
        if ($this->estArhiv()) {
            return;
        }

        if (trim($reason) === '') {
            throw new \DomainException('opencode.session.archive_reason_required');
        }

        $this->archivedAt = $now;
        $this->archiveReason = trim($reason);
        $this->updatedAt = $now;
    }

    public function zadatZagolovok(?string $title, int $now): void
    {
        $normalized = self::normalizeTitle($title);

        if ($normalized === '') {
            return;
        }

        $this->title = $normalized;
        $this->updatedAt = $now;
    }

    /**
     * Заголовок по умолчанию — по первому промпту, ровно один раз.
     */
    public function podpisatPoPromptu(string $prompt, int $now): void
    {
        // Название workspace задаётся пользователем и не подменяется промптом.
        if ($this->estWorkspace() || $this->title !== '') {
            return;
        }

        $this->title = self::normalizeTitle($prompt);
        $this->updatedAt = $now;
    }

    /**
     * Снимает архив после успешного восстановления workspace.
     */
    public function snovaAktivna(int $now): void
    {
        if (!$this->estArhiv()) {
            return;
        }

        $this->archivedAt = null;
        $this->archiveReason = null;
        $this->updatedAt = $now;
    }

    public function zapomnitVneshnyuyuSessiyu(string $externalSessionId, int $now): void
    {
        $this->externalSessionId = $externalSessionId;
        $this->updatedAt = $now;
    }

    public function otmetitIzmenenie(int $now): void
    {
        $this->updatedAt = $now;
    }

    /**
     * Дедупликация по unit_id и по проекту с сохранением порядка, проверка лимита.
     *
     * При создании сессии конфликт проектов — ошибка: пользователь явно указал
     * двух юнитов одного проекта. При восстановлении из хранилища исторические
     * данные не должны ломать чтение, поэтому лишние юниты одного проекта
     * отбрасываются молча (остаётся первый).
     *
     * @param list<PrivyazkaKUnitu> $units
     *
     * @return list<PrivyazkaKUnitu>
     */
    /**
     * @param list<ProektWorkspace> $projects
     */
    private static function proveritKolichestvoProektovWorkspace(array $projects): void
    {
        if ($projects === []) {
            throw new \DomainException('opencode.workspace.projects_required');
        }

        if (count($projects) > self::MAX_WORKSPACE_PROJECTS) {
            throw new \DomainException('opencode.workspace.too_many_projects');
        }
    }

    private static function normalizeUnits(array $units, bool $trebovatLimit): array
    {
        $unique = [];
        $seenUnits = [];
        $seenProjects = [];

        foreach ($units as $privyazka) {
            if (isset($seenUnits[$privyazka->unitId])) {
                continue;
            }

            if (isset($seenProjects[$privyazka->unitProjectId])) {
                if ($trebovatLimit) {
                    throw new \DomainException('opencode.session.unit_project_conflict');
                }

                continue;
            }

            $seenUnits[$privyazka->unitId] = true;
            $seenProjects[$privyazka->unitProjectId] = true;
            $unique[] = $privyazka;
        }

        if ($trebovatLimit && count($unique) > self::MAX_UNITS) {
            throw new \DomainException('opencode.session.too_many_units');
        }

        return $unique;
    }

    private static function normalizeTitle(?string $title): string
    {
        if ($title === null) {
            return '';
        }

        return mb_substr(trim($title), 0, self::TITLE_MAX_LENGTH);
    }
}
