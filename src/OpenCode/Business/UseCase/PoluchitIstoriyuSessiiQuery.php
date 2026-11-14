<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitIstoriyuSessii;
use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\ReadModel\HodOpenCodeReadModel;

/**
 * Полная история ходов сессии по возрастанию seq — то, что нужно показать
 * при открытии диалога.
 */
final class PoluchitIstoriyuSessiiQuery
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private HodOpenCodeRepository $turnRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetVypolnyatHodOpenCode $runner,
    ) {
    }

    /**
     * @return array<string, mixed>
     *
     * @psalm-return list<HodOpenCodeReadModel>
     *
     * @throws \DomainException если сессия не найдена или принадлежит другому пользователю
     */
    public function handle(PoluchitIstoriyuSessii $command): array
    {
        $accountId = $this->credentials->tekuschiiAccountId();

        $session = $this->sessionRepository->getById($command->sessionId);
        $session->trebovatVladelca($accountId);

        return array_map(
            fn (HodOpenCode $turn): HodOpenCodeReadModel => HodOpenCodeReadModel::izModela(
                $turn,
                $this->runner->kanalProgressa($turn->getWorkflowId()),
            ),
            $this->turnRepository->findBySessionId($session->getId()),
        );
    }
}
