<?php

declare(strict_types=1);

namespace App\Unitman\Business\ReadModel\LichniyToken;

use App\Unitman\Business\Model\LichniyToken\ObyomPrav;

/**
 * Read-модель личного токена (проекция `lichniy_token_list`).
 *
 * Содержит шифротекст: наружу через HTTP он не отдаётся, только hint.
 */
final class LichniyTokenList
{
    /**
     * @param string[] $proektySPushom
     */
    public function __construct(
        public string $id,
        public string $accountId,
        public string $repoId,
        public string $nazvanie,
        public string $tokenCipher,
        public string $tokenHint,
        public string $obyomPrav,
        public array $proektySPushom,
        public int $proverenoV,
        public bool $deleted,
    ) {
    }

    public function pokryvaetProekt(string $projectId): bool
    {
        if ($this->deleted) {
            return false;
        }

        if ($this->obyomPrav === ObyomPrav::Vse->value) {
            return true;
        }

        return in_array($projectId, $this->proektySPushom, true);
    }
}
