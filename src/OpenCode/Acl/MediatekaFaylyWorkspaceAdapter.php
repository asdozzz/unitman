<?php

declare(strict_types=1);

namespace App\OpenCode\Acl;

use App\Mediateka\Api\MediatekaApi;
use App\Mediateka\Business\Model\FaylUnita;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\ReadModel\FajlHodaReadModel;
use App\OpenCode\Business\ReadModel\WorkspaceFileReadModel;

/**
 * ACL-адаптер файлов workspace над публичным MediatekaApi.
 */
final class MediatekaFaylyWorkspaceAdapter implements UmeetUpravlyatFaylamiWorkspace
{
    public function __construct(private readonly MediatekaApi $mediatekaApi)
    {
    }

    public function poluchitFayly(string $workspaceId): array
    {
        return array_map(
            static fn (FaylUnita $file): WorkspaceFileReadModel => self::readModel($file),
            $this->mediatekaApi->poluchitFaylyWorkspace($workspaceId),
        );
    }

    public function poluchitFaylyPoImId(array $workspaceIds): array
    {
        $result = [];

        foreach ($this->mediatekaApi->poluchitFaylyWorkspacePoImId($workspaceIds) as $workspaceId => $files) {
            $result[$workspaceId] = array_map(
                static fn (FaylUnita $file): WorkspaceFileReadModel => self::readModel($file),
                $files,
            );
        }

        return $result;
    }

    public function dostupniDlyaPrivyazki(array $fileIds, string $accountId): bool
    {
        $ids = array_values(array_filter(
            array_map(static fn (string $id): string => trim($id), $fileIds),
            static fn (string $id): bool => $id !== '',
        ));

        if ($ids === []) {
            return false;
        }

        $owners = $this->mediatekaApi->poluchitVladelcevFaylov($ids);

        foreach ($ids as $fileId) {
            $owner = $owners[$fileId] ?? null;

            if ($owner === null) {
                return false;
            }

            if (($owner['kind'] ?? '') !== FaylUnita::KIND_WORKSPACE) {
                return false;
            }

            if (($owner['workspaceId'] ?? null) !== null) {
                return false;
            }

            if (($owner['uploadedBy'] ?? null) !== $accountId) {
                return false;
            }
        }

        return true;
    }

    public function prikrepit(string $workspaceId, array $fileIds): array
    {
        return array_map(
            static fn (FaylUnita $file): WorkspaceFileReadModel => self::readModel($file),
            $this->mediatekaApi->prikrepitFaylyKWorkspace($workspaceId, $fileIds),
        );
    }

    public function otmetitPrimenenimi(array $fileIds): void
    {
        $this->mediatekaApi->otmetitFaylyWorkspacePrimenenimi($fileIds);
    }

    public function poluchitSsylkiDlyaFaylov(array $fileIds): array
    {
        $result = [];
        $files = $this->mediatekaApi->poluchitFaylyPoImId($fileIds);

        foreach ($files as $file) {
            $result[$file->getId()] = [
                'name' => $file->getName(),
                'url' => $this->mediatekaApi->poluchitSsylkuDlyaFayla($file->getId()),
            ];
        }

        return $result;
    }

    public function udalitFayl(string $fileId): void
    {
        $this->mediatekaApi->udaliFayl($fileId);
    }

    public function zaregistrirovatFajlHoda(
        string $workspaceId,
        string $accountId,
        string $turnId,
        string $name,
        string $contentType,
        string $content,
        int $now,
    ): FajlHodaReadModel {
        return self::fajlHoda($this->mediatekaApi->zaregistrirovatFajlHoda(
            $workspaceId,
            $accountId,
            $turnId,
            $name,
            $contentType,
            $content,
            $now,
        ));
    }

    public function poluchitFajlHoda(string $turnId, string $fileId): ?FajlHodaReadModel
    {
        $file = $this->mediatekaApi->poluchitFajlHoda($turnId, $fileId);

        return $file === null ? null : self::fajlHoda($file);
    }

    public function poluchitStatistikuFajlovHoda(string $turnId): array
    {
        return $this->mediatekaApi->poluchitStatistikuFajlovHoda($turnId);
    }

    public function poluchitSoderzhimoeFajla(string $fileId, int $maxBytes): string
    {
        return $this->mediatekaApi->poluchitSoderzhimoeFajla($fileId, $maxBytes);
    }

    public function poluchitSsylkuDlyaFajlaHoda(string $turnId, string $fileId): ?string
    {
        return $this->mediatekaApi->poluchitSsylkuDlyaFajlaHoda($turnId, $fileId);
    }

    public function udalitFajlyWorkspace(string $workspaceId): void
    {
        $this->mediatekaApi->udalitFajlyWorkspace($workspaceId);
    }

    private static function fajlHoda(FaylUnita $file): FajlHodaReadModel
    {
        return new FajlHodaReadModel(
            $file->getId(),
            (string) $file->getOpencodeTurnId(),
            (string) $file->getWorkspaceId(),
            $file->getName(),
            $file->getSize(),
            $file->getMimeType(),
            $file->getCreatedAt(),
        );
    }

    private static function readModel(FaylUnita $file): WorkspaceFileReadModel
    {
        return new WorkspaceFileReadModel(
            $file->getId(),
            $file->getName(),
            $file->getSize(),
            $file->isPrimenen(),
            $file->getCreatedAt(),
        );
    }
}
