<?php

declare(strict_types=1);

namespace App\Runner\Business\Port;

use App\Runner\Business\Model\RepoCredentialGrant;

interface UmeetPoluchatKredensialyRepozitoriya
{
    /**
     * @throws \DomainException repo.credentials.denied|repo.credentials.unavailable|repo.credentials.invalid_request
     */
    public function poluchitKredensialy(
        string $runnerId,
        string $repoId,
        string $projectId,
        ?string $unitId = null,
    ): RepoCredentialGrant;
}
