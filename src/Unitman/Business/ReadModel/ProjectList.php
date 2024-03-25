<?php

namespace App\Unitman\Business\ReadModel;

use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;

final class ProjectList
{
    /**
     * @var ProjectUsersList[]
     * */
    public array $users;

    /**
     * @param ProjectUsersList[] $users
     * */
    public function __construct(
        public readonly string $id,
        public string $repoId,
        public string $code,
        public string $name,
        public string $mainBranch,
        public bool $isActive,
        public ProjectListStateType $state,
        public ?string $buildInfo,
        public ?string $removeInfo,
        public ?string $proxyHost,
        array $users = []
    )
    {
        $this->users = $users;
    }

}
