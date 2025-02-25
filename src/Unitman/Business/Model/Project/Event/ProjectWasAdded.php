<?php

namespace App\Unitman\Business\Model\Project\Event;

final class ProjectWasAdded
{
    public function __construct(
        public readonly string $id,
        public readonly string $repoId,
        public readonly string $projectCode,
        public readonly string $projectName,
        public readonly string $mainBranch,
        public readonly string $proxyHost = "",
        public readonly bool $avtosozdanie = false,
        public readonly bool $avtoobnovlenie = true,
        public readonly bool $avtoudalenie = true,
        public readonly bool $obnovlenieBezSbrosaPodgotovki = false,
    )
    {
    }

}
