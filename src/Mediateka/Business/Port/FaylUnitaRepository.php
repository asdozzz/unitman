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
     * Привязывает файл (в том числе черновик) к юниту. Ключ в хранилище не меняется.
     */
    public function prikrepitKUnitu(string $fileId, string $unitId): void;

    /**
     * @return string[] идентификаторы удалённых записей
     */
    public function udalitPoUnitu(string $unitId): array;

    public function udalit(string $id): void;
}
