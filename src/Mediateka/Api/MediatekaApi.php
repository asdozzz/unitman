<?php

declare(strict_types=1);

namespace App\Mediateka\Api;

use App\Mediateka\Business\Command\PrikrepitFaylyKUnitu;
use App\Mediateka\Business\Command\PrikrepitFaylyKWorkspace;
use App\Mediateka\Business\Command\ZagruzitFaylUnita;
use App\Mediateka\Business\Command\ZagruzitFaylWorkspace;
use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Port\FaylUnitaRepository;
use App\Mediateka\Business\UseCase\PoluchitFaylUnitaQuery;
use App\Mediateka\Business\UseCase\PoluchitFaylyUnitaQuery;
use App\Mediateka\Business\UseCase\PoluchitSoderzhimoeFajlaQuery;
use App\Mediateka\Business\UseCase\PoluchitSsylkuDlyaFaylaQuery;
use App\Mediateka\Business\UseCase\PrikrepitFaylyKUnituUseCase;
use App\Mediateka\Business\UseCase\PrikrepitFaylyKWorkspaceUseCase;
use App\Mediateka\Business\UseCase\ProveritFayliUnitaUseCase;
use App\Mediateka\Business\UseCase\UdaliFajlyWorkspaceUseCase;
use App\Mediateka\Business\UseCase\UdaliFayliUnitaUseCase;
use App\Mediateka\Business\UseCase\UdalitFaylUseCase;
use App\Mediateka\Business\UseCase\ZagruzitFaylUnitaUseCase;
use App\Mediateka\Business\UseCase\ZagruzitFaylWorkspaceUseCase;
use App\Mediateka\Business\UseCase\ZaregistrirovatFajlHodaUseCase;

/**
 * Публичный API модуля Mediateka. Другие модули обращаются только через этот класс.
 */
final class MediatekaApi
{
    public function __construct(
        private readonly FaylUnitaRepository $faylUnitaRepository,
        private readonly PoluchitFaylUnitaQuery $poluchitFaylUnitaQuery,
        private readonly PoluchitFaylyUnitaQuery $poluchitFaylyUnitaQuery,
        private readonly PoluchitSsylkuDlyaFaylaQuery $poluchitSsylkuDlyaFaylaQuery,
        private readonly ProveritFayliUnitaUseCase $proveritFayliUnitaUseCase,
        private readonly UdalitFaylUseCase $udalitFaylUseCase,
        private readonly UdaliFayliUnitaUseCase $udaliFayliUnitaUseCase,
        private readonly ZagruzitFaylUnitaUseCase $zagruzitFaylUnitaUseCase,
        private readonly PrikrepitFaylyKUnituUseCase $prikrepitFaylyKUnituUseCase,
        private readonly ZagruzitFaylWorkspaceUseCase $zagruzitFaylWorkspaceUseCase,
        private readonly PrikrepitFaylyKWorkspaceUseCase $prikrepitFaylyKWorkspaceUseCase,
        private readonly ZaregistrirovatFajlHodaUseCase $zaregistrirovatFajlHodaUseCase,
        private readonly PoluchitSoderzhimoeFajlaQuery $poluchitSoderzhimoeFajlaQuery,
        private readonly UdaliFajlyWorkspaceUseCase $udaliFajlyWorkspaceUseCase,
    ) {
    }

    public function zagruzitFaylUnita(ZagruzitFaylUnita $command): FaylUnita
    {
        return $this->zagruzitFaylUnitaUseCase->handle($command);
    }

    /**
     * Привязывает ранее загруженные файлы (черновики) к юниту.
     *
     * @param string[] $fileIds
     *
     * @return FaylUnita[]
     */
    public function prikrepitFaylyKUnitu(string $unitId, array $fileIds): array
    {
        return $this->prikrepitFaylyKUnituUseCase->handle(new PrikrepitFaylyKUnitu($unitId, $fileIds));
    }

    public function zagruzitFaylWorkspace(ZagruzitFaylWorkspace $command): FaylUnita
    {
        return $this->zagruzitFaylWorkspaceUseCase->handle($command);
    }

    /**
     * @param string[] $fileIds
     *
     * @return FaylUnita[]
     */
    public function prikrepitFaylyKWorkspace(string $workspaceId, array $fileIds): array
    {
        return $this->prikrepitFaylyKWorkspaceUseCase->handle(
            new PrikrepitFaylyKWorkspace($workspaceId, $fileIds),
        );
    }

    public function sushchestvuetLiFayl(string $fileId): bool
    {
        return $this->proveritFayliUnitaUseCase->sushchestvuetLi($fileId);
    }

    public function prinadlezhitLiFaylUnitu(string $fileId, string $unitId): bool
    {
        return $this->proveritFayliUnitaUseCase->prinadlezhitLiUnitu($fileId, $unitId);
    }

    public function poluchitFaylUnita(string $fileId): ?FaylUnita
    {
        return $this->poluchitFaylUnitaQuery->handle($fileId);
    }

    public function poluchitFaylUnitaPoImUnit(string $fileId, string $unitId): ?FaylUnita
    {
        $file = $this->faylUnitaRepository->findById($fileId);
        if ($file === null || $file->getUnitId() !== $unitId) {
            return null;
        }

        return $file;
    }

    /**
     * Архив каталога проекта: `project_id` совпадает, к юниту не привязан.
     */
    public function poluchitFaylProektaPoImProekta(string $fileId, string $projectId): ?FaylUnita
    {
        $file = $this->faylUnitaRepository->findById($fileId);
        if ($file === null || $file->getProjectId() !== $projectId) {
            return null;
        }

        return $file;
    }

    /**
     * Владельцы файлов для проверки допустимости `fileId` в томе юнита.
     *
     * @param string[] $fileIds
     *
     * @return array<string, array{unitId: ?string, projectId: ?string, workspaceId: ?string, kind: string, uploadedBy: ?string}>
     */
    public function poluchitVladelcevFaylov(array $fileIds): array
    {
        $result = [];
        foreach ($fileIds as $fileId) {
            $fileId = trim($fileId);
            $file = $this->faylUnitaRepository->findById($fileId);
            if ($file === null) {
                continue;
            }

            $result[$fileId] = [
                'unitId' => $file->getUnitId(),
                'projectId' => $file->getProjectId(),
                'workspaceId' => $file->getWorkspaceId(),
                'kind' => $file->getKind(),
                'uploadedBy' => $file->getUploadedBy(),
            ];
        }

        return $result;
    }

    /**
     * Файлы workspace (только привязанные).
     *
     * @return FaylUnita[]
     */
    public function poluchitFaylyWorkspace(string $workspaceId): array
    {
        return $this->faylUnitaRepository->najtiPoWorkspace($workspaceId);
    }

    /**
     * @param string[] $workspaceIds
     *
     * @return array<string, list<FaylUnita>>
     */
    public function poluchitFaylyWorkspacePoImId(array $workspaceIds): array
    {
        return $this->faylUnitaRepository->najtiPoWorkspaceIds($workspaceIds);
    }

    /**
     * @param string[] $fileIds
     *
     * @return FaylUnita[]
     */
    public function poluchitFaylyPoImId(array $fileIds): array
    {
        return $this->faylUnitaRepository->najtiPoImId($fileIds);
    }

    public function poluchitFaylWorkspacePoImWorkspace(string $fileId, string $workspaceId): ?FaylUnita
    {
        $file = $this->faylUnitaRepository->findById($fileId);
        if ($file === null || $file->getWorkspaceId() !== $workspaceId) {
            return null;
        }

        return $file;
    }

    /**
     * @param string[] $fileIds
     */
    public function otmetitFaylyWorkspacePrimenenimi(array $fileIds, ?int $appliedAt = null): void
    {
        $this->faylUnitaRepository->otmetitPrimenenie($fileIds, $appliedAt ?? time());
    }

    /**
     * @return FaylUnita[]
     */
    public function poluchitFaylyProekta(string $projectId): array
    {
        return $this->faylUnitaRepository->najtiPoProektu($projectId);
    }

    /**
     * Проверяет, что файлы можно прикрепить к юниту: они существуют
     * и ещё не привязаны к другому юниту (то есть являются черновиками).
     *
     * @param string[] $fileIds
     */
    public function vseFaylyDostupnyDlyaPrivyazki(array $fileIds): bool
    {
        foreach ($fileIds as $fileId) {
            $file = $this->faylUnitaRepository->findById(trim($fileId));
            if ($file === null || !$file->esliChernovik()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return FaylUnita[]
     */
    public function poluchitFaylyUnita(string $unitId): array
    {
        return $this->poluchitFaylyUnitaQuery->handle($unitId);
    }

    public function poluchitSsylkuDlyaFayla(string $fileId, ?int $ttl = null): string
    {
        return $this->poluchitSsylkuDlyaFaylaQuery->handle($fileId, $ttl);
    }

    public function udaliFayl(string $fileId): void
    {
        $this->udalitFaylUseCase->handle($fileId);
    }

    public function udaliFayliUnita(string $unitId): void
    {
        $this->udaliFayliUnitaUseCase->handle($unitId);
    }

    public function zaregistrirovatFajlHoda(
        string $workspaceId,
        string $accountId,
        string $turnId,
        string $name,
        string $contentType,
        string $content,
        int $now,
    ): FaylUnita {
        return $this->zaregistrirovatFajlHodaUseCase->handle(
            $workspaceId,
            $accountId,
            $turnId,
            $name,
            $contentType,
            $content,
            $now,
        );
    }

    public function poluchitFajlHoda(string $turnId, string $fileId): ?FaylUnita
    {
        return $this->faylUnitaRepository->najtiPoHoduImId($turnId, $fileId);
    }

    /**
     * @return array{count: int, totalSize: int}
     */
    public function poluchitStatistikuFajlovHoda(string $turnId): array
    {
        return $this->faylUnitaRepository->statistikaPoHodu($turnId);
    }

    public function poluchitSoderzhimoeFajla(string $fileId, int $maxBytes): string
    {
        return $this->poluchitSoderzhimoeFajlaQuery->handle($fileId, $maxBytes);
    }

    public function poluchitSsylkuDlyaFajlaHoda(string $turnId, string $fileId, ?int $ttl = null): ?string
    {
        if ($this->faylUnitaRepository->najtiPoHoduImId($turnId, $fileId) === null) {
            return null;
        }

        return $this->poluchitSsylkuDlyaFaylaQuery->handle($fileId, $ttl);
    }

    public function udalitFajlyWorkspace(string $workspaceId): void
    {
        $this->udaliFajlyWorkspaceUseCase->handle($workspaceId);
    }
}
