<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\ReadModel\SessiyaOpenCodeReadModel;

/**
 * Сессии текущего пользователя: фильтр по владельцу задаёт сам запрос,
 * поэтому чужую сессию получить этим эндпоинтом нельзя.
 */
final class PoluchitSpisokSessiyQuery
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
    ) {
    }

    /**
     * @return array<string, mixed>
     *
     * @psalm-return list<SessiyaOpenCodeReadModel>
     */
    public function handle(): array
    {
        $accountId = $this->credentials->tekuschiiAccountId();

        return array_map(
            static fn (SessiyaOpenCode $session): SessiyaOpenCodeReadModel => SessiyaOpenCodeReadModel::izModela($session),
            $this->sessionRepository->findByAccountId($accountId),
        );
    }
}
