<?php

declare(strict_types=1);

namespace App\Skill\Business\UseCase;

use App\Skill\Business\Command\PoluchitVersiiNavyka;
use App\Skill\Business\Model\VersiyaNavyka;
use App\Skill\Business\Port\NavykRepository;
use App\Skill\Business\ReadModel\VersiyaNavykaReadModel;
use App\Skill\Business\Utils\ProverkaPravNavykov;

/**
 * История версий навыка (от новой к старой).
 */
final class PoluchitVersiiNavykaQuery
{
    public function __construct(
        private readonly NavykRepository $repository,
        private readonly ProverkaPravNavykov $prava,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(PoluchitVersiiNavyka $command): array
    {
        $navyk = $this->repository->findById(trim($command->id));

        if ($navyk === null) {
            throw new \DomainException('skill.not_found');
        }

        $accountId = $this->prava->tekuschiiAccountId();
        $this->prava->trebovatChtenie($navyk, $accountId);

        return array_map(
            static fn (VersiyaNavyka $versiya): array => VersiyaNavykaReadModel::izModela($versiya)->toArray(),
            $this->repository->listVersions($navyk->getId()),
        );
    }
}
