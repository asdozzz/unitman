<?php

declare(strict_types=1);

namespace App\Runner\Acl;

use App\Account\Api\AccountApi;
use App\Runner\Business\Model\OpenCode\RekvizityOpenCode;
use App\Runner\Business\Port\UmeetPoluchatRekvizityOpenCode;

/**
 * Антикоррупционный слой между Runner и Account.
 *
 * Единственная точка, где Runner узнаёт про Account: остальной модуль работает
 * с портом [`UmeetPoluchatRekvizityOpenCode`](src/Runner/Business/Port/UmeetPoluchatRekvizityOpenCode.php:17)
 * и локальной моделью [`RekvizityOpenCode`](src/Runner/Business/Model/OpenCode/RekvizityOpenCode.php:17).
 */
final class AccountAdapter implements UmeetPoluchatRekvizityOpenCode
{
    public function __construct(private AccountApi $accountApi)
    {
    }

    public function poluchitRekvizity(string $accountId, string $credentialId): RekvizityOpenCode
    {
        $secret = $this->accountApi->poluchitRekvizityOpenCodeDlyVladelca($accountId, $credentialId);

        return new RekvizityOpenCode(
            $secret->id,
            $secret->url,
            $secret->model,
            $secret->apiKey,
        );
    }
}
