<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\OtmetitUvedomleniyaProchitannimi;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UvedomleniyaOpenCodeRepository;

/**
 * Атомарный захват уведомлений текущим устройством или вкладкой.
 *
 * Возвращаются только идентификаторы, которые именно этот запрос перевёл из
 * непрочитанных в прочитанные. Чужие уведомления и повторный захват уже
 * прочитанных не проходят фильтр репозитория по `account_id` и `read_at`.
 */
final class OtmetitUvedomleniyaProchitannimiUseCase
{
    public function __construct(
        private UvedomleniyaOpenCodeRepository $notifications,
        private UmeetProveryatRekvizityOpenCode $credentials,
    ) {
    }

    /**
     * @return list<string>
     *
     * @throws \DomainException если список пуст или превышает лимит
     */
    public function handle(OtmetitUvedomleniyaProchitannimi $command): array
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $ids = $this->unikalnieId($command->ids);

        if ($ids === []) {
            throw new \DomainException('opencode.notification.ids_required');
        }

        if (count($ids) > OtmetitUvedomleniyaProchitannimi::MAX_IDS) {
            throw new \DomainException('opencode.notification.too_many_ids');
        }

        return $this->notifications->zahvatitNeprochitannie($accountId, $ids, time());
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function unikalnieId(array $ids): array
    {
        $unique = [];

        foreach ($ids as $id) {
            $normalized = trim($id);

            if ($normalized === '') {
                continue;
            }

            $unique[$normalized] = true;
        }

        return array_keys($unique);
    }
}
