<?php

declare(strict_types=1);

namespace App\AiChat\Business\UseCase;

use App\AiChat\Business\Port\RekvizityDlyaSessiiAiChata;
use App\AiChat\Business\Port\SessiyaAiChataRepository;

/**
 * Полное удаление сессии AI-чата вместе с историей.
 *
 * Разрешено только для архивной сессии: активную сначала нужно архивировать,
 * чтобы случайное удаление не уничтожило текущий диалог.
 */
final class UdalitSessiyuAiChataUseCase
{
    public function __construct(
        private readonly SessiyaAiChataRepository $repository,
        private readonly RekvizityDlyaSessiiAiChata $rekvizity,
    ) {
    }

    /**
     * @throws \DomainException `ai.chat.session_not_found`, если сессии нет или она чужая;
     *                          `ai.chat.session_not_archived`, если сессия ещё активна
     */
    public function vypolnit(string $sessionId): void
    {
        $accountId = $this->rekvizity->tekuschiiAccountId();

        $session = $this->repository->naytiPoId($sessionId);

        if ($session === null) {
            throw new \DomainException('ai.chat.session_not_found');
        }

        $session->trebovatVladelca($accountId);

        if (!$session->arhivirovana()) {
            throw new \DomainException('ai.chat.session_not_archived');
        }

        $this->repository->udalit($session);
    }
}
