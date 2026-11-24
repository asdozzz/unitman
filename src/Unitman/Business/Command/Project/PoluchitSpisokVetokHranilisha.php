<?php

namespace App\Unitman\Business\Command\Project;

/**
 * Запрос списка веток проекта в хранилище до создания проекта в Unitman.
 *
 * В отличие от PoluchitSpisokVetokProekta, здесь проект ещё не создан:
 * ветки запрашиваются по паре «репозиторий + код проекта в хранилище».
 */
final class PoluchitSpisokVetokHranilisha
{
    public function __construct(
        public readonly string $repoId,
        public readonly string $projectCode,
        public readonly ?string $query = null,
    ) {
    }
}
