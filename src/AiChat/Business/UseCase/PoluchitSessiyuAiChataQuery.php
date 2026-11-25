<?php

declare(strict_types=1);

namespace App\AiChat\Business\UseCase;

use App\AiChat\Business\Model\SoobshenieAiChata;
use App\AiChat\Business\Port\RekvizityDlyaSessiiAiChata;
use App\AiChat\Business\Port\SessiyaAiChataRepository;
use App\AiChat\Business\Port\SoobshenieAiChataRepository;
use App\AiChat\Business\ReadModel\SessiyaAiChataReadModel;
use App\AiChat\Business\ReadModel\SessiyaAiChataSPolnoiIstorieiReadModel;
use App\AiChat\Business\ReadModel\SoobshenieAiChataReadModel;
use Psr\Clock\ClockInterface;

/**
 * Сессия AI-чата вместе с полной историей сообщений.
 *
 * Истёкшая сессия при чтении автоматически архивируется и отдаётся в режиме
 * только для просмотра: ошибки нет, меняется лишь флаг `archived`.
 */
final class PoluchitSessiyuAiChataQuery
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
    public function vypolnit(string $sessionId): SessiyaAiChataSPolnoiIstorieiReadModel
    {
        $now = $this->clock->now()->getTimestamp();
        $accountId = $this->rekvizity->tekuschiiAccountId();

        $session = $this->repository->naytiPoId($sessionId);

        if ($session === null) {
            throw new \DomainException('ai.chat.session_not_found');
        }

        $session->trebovatVladelca($accountId);

        if ($session->arhivirovatPriIstechenii($now)) {
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

        return new SessiyaAiChataSPolnoiIstorieiReadModel(
            SessiyaAiChataReadModel::izDomena(
                $session,
                $this->preview($soobsheniya),
                \count($soobsheniya),
                $credentialDeleted,
            ),
            array_map(
                static fn (SoobshenieAiChata $soobshenie): SoobshenieAiChataReadModel
                    => SoobshenieAiChataReadModel::izDomena($soobshenie),
                $soobsheniya,
            ),
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
