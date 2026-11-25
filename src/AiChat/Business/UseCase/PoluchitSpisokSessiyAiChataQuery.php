<?php

declare(strict_types=1);

namespace App\AiChat\Business\UseCase;

use App\AiChat\Business\Model\FiltrSessiyAiChata;
use App\AiChat\Business\Port\SessiyaAiChataRepository;
use App\AiChat\Business\ReadModel\SpisokSessiyAiChataReadModel;
use Psr\Clock\ClockInterface;

/**
 * Список сессий AI-чата аккаунта.
 *
 * Перед выборкой истёкшие сессии массово архивируются, чтобы список не
 * показывал формально активные, но уже недоступные для продолжения сессии.
 * Возвращается страница с метаданными пагинации; пустые сессии отсекает
 * репозиторий, а в каждой карточке ровно одно превью первого сообщения.
 */
final class PoluchitSpisokSessiyAiChataQuery
{
    public function __construct(
        private readonly SessiyaAiChataRepository $repository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function vypolnit(string $accountId, FiltrSessiyAiChata $filtr): SpisokSessiyAiChataReadModel
    {
        $this->repository->arhivirovatIstekchie($accountId, $this->clock->now()->getTimestamp());

        return $this->repository->spisok($accountId, $filtr);
    }
}
