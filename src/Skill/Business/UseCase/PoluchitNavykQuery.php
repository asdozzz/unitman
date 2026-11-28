<?php

declare(strict_types=1);

namespace App\Skill\Business\UseCase;

use App\Skill\Business\Command\PoluchitNavyk;
use App\Skill\Business\Port\NavykRepository;
use App\Skill\Business\ReadModel\NavykDetailReadModel;
use App\Skill\Business\Utils\ProverkaPravNavykov;

/**
 * Полная карточка навыка с содержимым и признаком `canEdit`.
 */
final class PoluchitNavykQuery
{
    public function __construct(
        private readonly NavykRepository $repository,
        private readonly ProverkaPravNavykov $prava,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(PoluchitNavyk $command): array
    {
        $navyk = $this->repository->findById(trim($command->id));

        if ($navyk === null) {
            throw new \DomainException('skill.not_found');
        }

        $accountId = $this->prava->tekuschiiAccountId();
        $this->prava->trebovatChtenie($navyk, $accountId);

        return NavykDetailReadModel::izModela(
            $navyk,
            $this->prava->mozhetRedaktirovat($navyk, $accountId),
        )->toArray();
    }
}
