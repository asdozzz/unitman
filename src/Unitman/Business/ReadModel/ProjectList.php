<?php

namespace App\Unitman\Business\ReadModel;

use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;

final class ProjectList
{
    public function __construct(
        public readonly string $id,
        public readonly string $repoId,
        public readonly string $code,
        public readonly string $name,
        public readonly string $mainBranch,
        public readonly bool $isActive,
        public readonly ProjectListStateType $state,
        public readonly ?string $buildInfo,
        public readonly ?string $removeInfo
    )
    {
    }

}
