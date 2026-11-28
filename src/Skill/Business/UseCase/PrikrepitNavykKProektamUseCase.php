<?php

declare(strict_types=1);

namespace App\Skill\Business\UseCase;

use App\Skill\Business\Command\PrikrepitNavykKProektam;
use App\Skill\Business\Port\NavykRepository;
use App\Skill\Business\Utils\ProverkaNavyka;
use App\Skill\Business\Utils\ProverkaPravNavykov;

/**
 * Прикрепление проектного навыка к дополнительным проектам.
 *
 * Глобальный навык доступен во всех проектах и не привязывается. Для
 * прикрепления нужны права `ADMIN` и на уже привязанные, и на новые проекты.
 */
final class PrikrepitNavykKProektamUseCase
{
    public function __construct(
        private readonly NavykRepository $repository,
        private readonly ProverkaPravNavykov $prava,
    ) {
    }

    /**
     * @return array{projectIds: list<string>}
     */
    public function handle(PrikrepitNavykKProektam $command): array
    {
        $navyk = $this->repository->findById(trim($command->id));

        if ($navyk === null) {
            throw new \DomainException('skill.not_found');
        }

        if ($navyk->isGlobal()) {
            throw new \DomainException('skill.scope.global_cannot_attach');
        }

        $novye = ProverkaNavyka::normalizovatProekty($command->projectIds);

        $this->prava->trebovatAdministratoraProektov($navyk->getProjectIds());
        $this->prava->trebovatAdministratoraProektov($novye);

        $actor = $this->prava->tekuschiiAccountId();

        $this->repository->attachToProjects($navyk->getId(), $novye, $actor, time());

        return ['projectIds' => $this->obedinenie($navyk->getProjectIds(), $novye)];
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     *
     * @return list<string>
     */
    private function obedinenie(array $a, array $b): array
    {
        $rezultat = $a;

        foreach ($b as $element) {
            if (!\in_array($element, $rezultat, true)) {
                $rezultat[] = $element;
            }
        }

        return $rezultat;
    }
}
