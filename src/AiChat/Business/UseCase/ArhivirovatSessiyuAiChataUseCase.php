<?php

declare(strict_types=1);

namespace App\AiChat\Business\UseCase;

use App\AiChat\Business\Model\PrichinaArhivaciiSessiiAiChata;
use App\AiChat\Business\Model\SoobshenieAiChata;
use App\AiChat\Business\Port\RekvizityDlyaSessiiAiChata;
use App\AiChat\Business\Port\SessiyaAiChataRepository;
use App\AiChat\Business\Port\SoobshenieAiChataRepository;
use App\AiChat\Business\ReadModel\SessiyaAiChataReadModel;
use Psr\Clock\ClockInterface;

/**
 * Ручная архивация сессии AI-чата.
 *
 * Идемпотентна: повторный вызов не меняет время и причину архивации и ничего
 * не пишет в БД. Архивная сессия остаётся доступной для чтения.
 */
final class ArhivirovatSessiyuAiChataUseCase
{
    public function __construct(
        private readonly SessiyaAiChataRepository $repository,
        private readonly SoobshenieAiChataRepository $soobsheniya,
        private readonly RekvizityDlyaSessiiAiChata $rekvizity,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws \DomainException `ai.chat.session_not_found`
     */
    public function vypolnit(string $sessionId): SessiyaAiChataReadModel
    {
        $now = $this->clock->now()->getTimestamp();
        $accountId = $this->rekvizity->tekuschiiAccountId();

        $session = $this->repository->naytiPoId($sessionId);

        if ($session === null) {
            throw new \DomainException('ai.chat.session_not_found');
        }

        $session->trebovatVladelca($accountId);

        if (!$session->arhivirovana()) {
            $session->arhivirovat(PrichinaArhivaciiSessiiAiChata::Manual->value, $now);
            $this->repository->sohranit($session);
        }

        $soobsheniya = $this->soobsheniya->vse($session->id());

        $credentialId = $session->credentialId();
        $credentialDeleted = $credentialId !== null
            && !\in_array(
                $credentialId,
                $this->rekvizity->identifikatoryRekvizitov($accountId),
                true,
            );

        return SessiyaAiChataReadModel::izDomena(
            $session,
            $this->preview($soobsheniya),
            \count($soobsheniya),
            $credentialDeleted,
        );
    }

    /**
     * @param list<SoobshenieAiChata> $soobsheniya
     */
    private function preview(array $soobsheniya): string
    {
        if ($soobsheniya === []) {
            return '';
        }

        return SessiyaAiChataReadModel::sdelatPreview($soobsheniya[0]->content);
    }
}
