<?php

namespace App\Unitman\Business\ReadModel;

use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;

final class ProjectList
{
    /**
     * @var ProjectUsersList[]
     * */
    public array $users;

    /**
     * @var RunnerJobStep[]
     * */
    public array $buildInfo;

    /**
     * @var RunnerJobStep[]
     * */
    public array $removeInfo;

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
        array $buildInfo = [],
        array $removeInfo = [],
        public ?string $proxyHost = null,
        array $users = []
    )
    {
        $this->users = $users;
        $this->buildInfo = $buildInfo;
        $this->removeInfo = $removeInfo;
    }

}
