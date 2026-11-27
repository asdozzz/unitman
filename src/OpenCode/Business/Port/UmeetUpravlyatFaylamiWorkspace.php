<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\ReadModel\FajlHodaReadModel;
use App\OpenCode\Business\ReadModel\WorkspaceFileReadModel;

/**
 * Файлы workspace: чтение, привязка черновиков, ссылки и удаление.
 *
 * Реализация — ACL-адаптер над публичным MediatekaApi. OpenCode не знает
 * внутренних классов Mediateka, а Mediateka не знает о workspace.
 */
interface UmeetUpravlyatFaylamiWorkspace
{
    /**
     * @return list<WorkspaceFileReadModel>
     */
    public function poluchitFayly(string $workspaceId): array;

    /**
     * @param string[] $workspaceIds
     *
     * @return array<string, list<WorkspaceFileReadModel>>
     */
    public function poluchitFaylyPoImId(array $workspaceIds): array;

    /**
     * Проверяет, что файлы можно привязать: они существуют, являются
     * черновиками workspace и загружены текущим пользователем.
     *
     * @param string[] $fileIds
     */
    public function dostupniDlyaPrivyazki(array $fileIds, string $accountId): bool;

    /**
     * @param string[] $fileIds
     *
     * @return list<WorkspaceFileReadModel>
     */
    public function prikrepit(string $workspaceId, array $fileIds): array;

    /**
     * @param string[] $fileIds
     */
    public function otmetitPrimenenimi(array $fileIds): void;

    /**
     * Подписанные ссылки на скачивание: fileId => [name, url].
     *
     * @param string[] $fileIds
     *
     * @return array<string, array{name: string, url: string}>
     */
    public function poluchitSsylkiDlyaFaylov(array $fileIds): array;

    public function udalitFayl(string $fileId): void;

    /**
     * Регистрирует файл-артефакт, созданный агентом в ходе OpenCode.
     *
     * @throws \DomainException mediateka.file.name_is_invalid, mediateka.file.type_is_forbidden,
     *         mediateka.file.content_is_invalid, mediateka.workspace.file_is_empty,
     *         mediateka.workspace.file_is_too_large, mediateka.file.workspace_id_is_empty,
     *         mediateka.file.opencode_turn_id_is_empty
     */
    public function zaregistrirovatFajlHoda(
        string $workspaceId,
        string $accountId,
        string $turnId,
        string $name,
        string $contentType,
        string $content,
        int $now,
    ): FajlHodaReadModel;

    /**
     * Файл хода по паре (turnId, fileId); null — файл не найден или чужой ход.
     */
    public function poluchitFajlHoda(string $turnId, string $fileId): ?FajlHodaReadModel;

    /**
     * @return array{count: int, totalSize: int}
     */
    public function poluchitStatistikuFajlovHoda(string $turnId): array;

    /**
     * @throws \DomainException mediateka.file.not_found, mediateka.file.too_large,
     *         mediateka.file.content_is_invalid
     */
    public function poluchitSoderzhimoeFajla(string $fileId, int $maxBytes): string;

    public function poluchitSsylkuDlyaFajlaHoda(string $turnId, string $fileId): ?string;

    public function udalitFajlyWorkspace(string $workspaceId): void;
}
