<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitSpisokSessiy;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCodeFilter;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\ReadModel\SessiyaOpenCodeReadModel;

/**
 * Сессии текущего пользователя: фильтр по владельцу задаёт сам запрос,
 * поэтому чужую сессию получить этим эндпоинтом нельзя.
 *
 * По умолчанию показываются все сессии, включая архивные: архив — это состояние,
 * а не удаление, и пользователь должен видеть свои прошлые диалоги.
 */
final class PoluchitSpisokSessiyQuery
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
    ) {
    }

    /**
     * @return list<SessiyaOpenCodeReadModel>
     */
    public function handle(PoluchitSpisokSessiy $query): array
    {
        $accountId = $this->credentials->tekuschiiAccountId();

        $filter = new SessiyaOpenCodeFilter($query->onlyActive, $query->search);

        return array_map(
            static fn (SessiyaOpenCode $session): SessiyaOpenCodeReadModel => SessiyaOpenCodeReadModel::izModela($session),
            $this->sessionRepository->findByAccountId($accountId, $filter),
        );
    }
}
