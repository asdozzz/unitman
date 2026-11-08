<?php

declare(strict_types=1);

namespace App\Mediateka\Api;

use App\Mediateka\Business\Command\PrikrepitFaylyKUnitu;
use App\Mediateka\Business\Command\ZagruzitFaylUnita;
use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Port\FaylUnitaRepository;
use App\Mediateka\Business\UseCase\PoluchitFaylUnitaQuery;
use App\Mediateka\Business\UseCase\PoluchitFaylyUnitaQuery;
use App\Mediateka\Business\UseCase\PoluchitSsylkuDlyaFaylaQuery;
use App\Mediateka\Business\UseCase\PrikrepitFaylyKUnituUseCase;
use App\Mediateka\Business\UseCase\ProveritFayliUnitaUseCase;
use App\Mediateka\Business\UseCase\UdaliFayliUnitaUseCase;
use App\Mediateka\Business\UseCase\UdalitFaylUseCase;
use App\Mediateka\Business\UseCase\ZagruzitFaylUnitaUseCase;

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
        private readonly PrikrepitFaylyKUnituUseCase $prikrepitFaylyKUnituUseCase
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
     * @return array<string, array{unitId: ?string, projectId: ?string}>
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
            ];
        }

        return $result;
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
}
