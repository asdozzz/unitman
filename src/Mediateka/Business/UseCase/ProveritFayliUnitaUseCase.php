<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Port\FaylUnitaRepository;

/**
 * Проверка, что все перечисленные файлы существуют и прикреплены к указанному юниту.
 * Используется слоем Unitman при заполнении значений переменных конфига.
 */
final class ProveritFayliUnitaUseCase
{
    public function __construct(private readonly FaylUnitaRepository $faylUnitaRepository)
    {
    }

    public function sushchestvuetLi(string $fileId): bool
    {
        return $this->faylUnitaRepository->findById($fileId) !== null;
    }

    public function prinadlezhitLiUnitu(string $fileId, string $unitId): bool
    {
        $file = $this->faylUnitaRepository->findById($fileId);

        return $file !== null && $file->getUnitId() === $unitId;
    }

    /**
     * @param string[] $fileIds
     */
    public function vseSushchestvuyutLi(array $fileIds, ?string $unitId = null): bool
    {
        foreach ($fileIds as $fileId) {
            if ($unitId === null) {
                if (!$this->sushchestvuetLi($fileId)) {
                    return false;
                }

                continue;
            }

            if (!$this->prinadlezhitLiUnitu($fileId, $unitId)) {
                return false;
            }
        }

        return true;
    }
}
