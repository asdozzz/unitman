<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Port;

use App\Mediateka\Business\Model\FaylUnita;

interface FaylUnitaRepository
{
    public function save(FaylUnita $file): void;

    public function getById(string $id): FaylUnita;

    public function findById(string $id): ?FaylUnita;

    /**
     * @return FaylUnita[]
     */
    public function najtiPoUnitu(string $unitId): array;

    /**
     * Архивы каталога проекта (unit_id is null, project_id = $projectId).
     *
     * @return FaylUnita[]
     */
    public function najtiPoProektu(string $projectId): array;

    /**
     * Файлы workspace в порядке загрузки.
     *
     * @return FaylUnita[]
     */
    public function najtiPoWorkspace(string $workspaceId): array;

    /**
     * Файлы хода OpenCode в порядке создания.
     *
     * @return FaylUnita[]
     */
    public function najtiPoHodu(string $turnId): array;

    /**
     * Файл хода по идентификатору файла в рамках хода.
     */
    public function najtiPoHoduImId(string $turnId, string $fileId): ?FaylUnita;

    /**
     * Файл хода по нормализованному имени в рамках хода (идемпотентность).
     */
    public function najtiPoHoduImImeni(string $turnId, string $name): ?FaylUnita;

    /**
     * Сводка по файлам хода: количество и суммарный размер.
     *
     * @return array{count: int, totalSize: int}
     */
    public function statistikaPoHodu(string $turnId): array;

    /**
     * Файлы workspace для набора идентификаторов workspace (без N+1).
     *
     * @param string[] $workspaceIds
     *
     * @return array<string, list<FaylUnita>> workspaceId => files
     */
    public function najtiPoWorkspaceIds(array $workspaceIds): array;

    /**
     * @param string[] $fileIds
     *
     * @return FaylUnita[]
     */
    public function najtiPoImId(array $fileIds): array;

    /**
     * Привязывает файл (в том числе черновик) к юниту. Ключ в хранилище не меняется.
     */
    public function prikrepitKUnitu(string $fileId, string $unitId): void;

    /**
     * Привязывает черновик workspace к workspace. Ключ в хранилище не меняется.
     */
    public function prikrepitKWorkspace(string $fileId, string $workspaceId): void;

    /**
     * Отмечает файлы workspace применёнными к контейнеру.
     *
     * @param string[] $fileIds
     */
    public function otmetitPrimenenie(array $fileIds, int $appliedAt): void;

    /**
     * @return string[] идентификаторы удалённых записей
     */
    public function udalitPoUnitu(string $unitId): array;

    /**
     * @return string[] идентификаторы удалённых записей
     */
    public function udalitPoWorkspace(string $workspaceId): array;

    public function udalit(string $id): void;
}
