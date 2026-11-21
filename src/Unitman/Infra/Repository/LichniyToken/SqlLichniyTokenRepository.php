<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Repository\LichniyToken;

use App\Unitman\Business\Model\LichniyToken;
use App\Unitman\Business\Model\LichniyToken\LichniyTokenId;
use App\Unitman\Business\Port\LichniyToken\LichniyTokenRepository;
use App\Utils\EventSauce\ProjectionsManager;

final class SqlLichniyTokenRepository implements LichniyTokenRepository
{
    public function __construct(private ProjectionsManager $projectionsManager)
    {
    }

    public function getById(string $id): LichniyToken
    {
        $token = $this->findById($id);

        if ($token === null) {
            throw new \DomainException('lichniy_token.ne_nayden');
        }

        return $token;
    }

    public function findById(string $id): ?LichniyToken
    {
        $token = $this->projectionsManager->retrieve(LichniyToken::class, LichniyTokenId::fromString($id));

        /** @var LichniyToken $token */
        if ($token->aggregateRootVersion() === 0) {
            return null;
        }

        return $token;
    }

    public function save(LichniyToken $token): void
    {
        $this->projectionsManager->persistAndPullProjections($token);
    }
}
