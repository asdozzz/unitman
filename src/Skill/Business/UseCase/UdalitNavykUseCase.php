<?php

declare(strict_types=1);

namespace App\Skill\Business\UseCase;

use App\Skill\Business\Command\UdalitNavyk;
use App\Skill\Business\Port\NavykRepository;
use App\Skill\Business\Utils\ProverkaPravNavykov;

/**
 * Мягкое удаление навыка (архивация).
 */
final class UdalitNavykUseCase
{
    public function __construct(
        private readonly NavykRepository $repository,
        private readonly ProverkaPravNavykov $prava,
    ) {
    }

    public function handle(UdalitNavyk $command): void
    {
        $navyk = $this->repository->findById(trim($command->id));

        if ($navyk === null) {
            throw new \DomainException('skill.not_found');
        }

        $actor = $this->prava->tekuschiiAccountId();
        $this->prava->trebovatRedaktirovanie($navyk, $actor);

        $navyk->arhivirovat($actor, time());
        $this->repository->archive($navyk);
    }
}
