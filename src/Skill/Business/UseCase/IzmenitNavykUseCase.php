<?php

declare(strict_types=1);

namespace App\Skill\Business\UseCase;

use App\Skill\Business\Command\IzmenitNavyk;
use App\Skill\Business\Port\NavykRepository;
use App\Skill\Business\Utils\ProverkaPravNavykov;

/**
 * Частичное изменение навыка.
 *
 * Версия поднимается только при изменении name/description/content; смена
 * slug/области версию не поднимает (см. NavykOpenCode::izmenit()).
 */
final class IzmenitNavykUseCase
{
    public function __construct(
        private readonly NavykRepository $repository,
        private readonly ProverkaPravNavykov $prava,
    ) {
    }

    /**
     * @return array{id: string, version: int}
     */
    public function handle(IzmenitNavyk $command): array
    {
        $navyk = $this->repository->findById(trim($command->id));

        if ($navyk === null) {
            throw new \DomainException('skill.not_found');
        }

        $actor = $this->prava->tekuschiiAccountId();
        $this->prava->trebovatRedaktirovanie($navyk, $actor);

        $navyk->izmenit(
            $actor,
            time(),
            null,
            $command->name,
            $command->description,
            $command->content,
        );

        $this->repository->update($navyk);

        return ['id' => $navyk->getId(), 'version' => $navyk->getVersion()];
    }
}
