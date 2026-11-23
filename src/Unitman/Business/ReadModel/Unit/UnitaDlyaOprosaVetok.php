<?php

namespace App\Unitman\Business\ReadModel\Unit;

/**
 * Юнит, ветку которого нужно опросить из-за отсутствия Git-вебхука.
 *
 * Поля проекта продублированы в каждой строке, чтобы polling мог работать
 * без отдельного чтения агрегата проекта на каждый юнит.
 */
final readonly class UnitaDlyaOprosaVetok
{
    public function __construct(
        public string $unitId,
        public string $projectId,
        public string $projectCode,
        public string $repoId,
        public string $branch,
        public string $mainBranch,
        public ?int $unixtimePoslednegoObnovleniyaVHranilishe,
        public bool $avtoobnovlenie,
        public bool $avtoudalenie,
    ) {
    }
}
