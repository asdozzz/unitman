<?php

namespace App\Unitman\Business\ReadModel;

use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use App\Unitman\Business\ReadModel\ProjectList\NastroikiHukaProekta;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListVariable;

final class ProjectList
{
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
        public ?string $proxyHost = null,
        public array $users = [],
        public array $variables = [],
        public bool $waitResultRunner = false,
        public int $memoryLimit = 3072
    )
    {
    }

}
