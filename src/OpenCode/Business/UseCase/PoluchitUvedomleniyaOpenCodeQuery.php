<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitUvedomleniyaOpenCode;
use App\OpenCode\Business\Port\UmeetOpredelyatKanalUvedomleniyOpenCode;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UvedomleniyaOpenCodeRepository;
use App\OpenCode\Business\ReadModel\UvedomleniyaOpenCodeReadModel;

/**
 * Непрочитанные уведомления и имя канала для текущего пользователя.
 *
 * Аккаунт берётся из JWT, поэтому фильтрация по `account_id` выполняется
 * репозиторием и чужие уведомления в ответ не попадают.
 */
final class PoluchitUvedomleniyaOpenCodeQuery
{
    public function __construct(
        private UvedomleniyaOpenCodeRepository $notifications,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetOpredelyatKanalUvedomleniyOpenCode $kanaly,
    ) {
    }

    public function handle(PoluchitUvedomleniyaOpenCode $command): UvedomleniyaOpenCodeReadModel
    {
        $accountId = $this->credentials->tekuschiiAccountId();

        $limit = $this->normalizovatLimit($command->limit);

        return new UvedomleniyaOpenCodeReadModel(
            $this->kanaly->kanalDlyaAccount($accountId),
            $this->notifications->findNeprochitannie($accountId, $limit),
        );
    }

    private function normalizovatLimit(int $limit): int
    {
        if ($limit <= 0) {
            return PoluchitUvedomleniyaOpenCode::DEFAULT_LIMIT;
        }

        return min($limit, PoluchitUvedomleniyaOpenCode::MAX_LIMIT);
    }
}
