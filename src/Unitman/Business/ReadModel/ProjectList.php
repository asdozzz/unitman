<?php

namespace App\Unitman\Business\ReadModel;

use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use App\Unitman\Business\ReadModel\ProjectList\NastroikiHukaProekta;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListVariable;

final class ProjectList
{
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
     * @param ProjectListVariable[] $variables
     * */
    public function __construct(
        public readonly string $id,
        public string $repoId,
        public string $code,
        public string $name,
        public string $mainBranch,
        public bool $isActive,
        public ProjectListStateType $state,
        public NastroikiHukaProekta $nastroikiHukaProekta,
        array $buildInfo = [],
        array $removeInfo = [],
        public ?string $proxyHost = null,
        public array $users = [],
        public array $variables = [],
    )
    {
        $this->buildInfo = $buildInfo;
        $this->removeInfo = $removeInfo;
    }

}
