<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\LichniyToken\Event\LichniyTokenDobavlen;
use App\Unitman\Business\Model\LichniyToken\Event\LichniyTokenUdalen;
use App\Unitman\Business\Model\LichniyToken\Event\PravaLichnogoTokenaObnovleni;
use App\Unitman\Business\ReadModel\LichniyToken\LichniyTokenList;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Repository\LichniyToken\SqlLichniyTokenListRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;

final class LichniyTokenListProjection extends AbstractProjection implements LichniyTokenProjection
{
    public function __construct(private SqlLichniyTokenListRepository $repository)
    {
    }

    public function isSyncProjection(): bool
    {
        return true;
    }

    public function init(): void
    {
        $this->repository->init();
    }

    public function destroy(): void
    {
        $this->repository->destroy();
    }

    public function getProjectionName(): string
    {
        return 'lichniy_token_list';
    }

    public function reset(): void
    {
        $this->repository->truncate();
    }

    public function resetById(string $id): void
    {
        $this->repository->resetById($id);
    }

    public function getStreamName(): StreamName
    {
        return new StreamName(UnitmanClassNameMapEnum::LichniyToken->value);
    }

    public function handleLichniyTokenDobavlen(LichniyTokenDobavlen $event): void
    {
        $this->repository->insert(new LichniyTokenList(
            $event->tokenId,
            $event->accountId,
            $event->repoId,
            $event->nazvanie,
            $event->tokenCipher,
            $event->tokenHint,
            $event->obyomPrav,
            $event->proektySPushom,
            $event->proverenoV,
            false,
        ));
    }

    public function handleLichniyTokenUdalen(LichniyTokenUdalen $event): void
    {
        $this->repository->markDeleted($event->tokenId);
    }

    public function handlePravaLichnogoTokenaObnovleni(PravaLichnogoTokenaObnovleni $event): void
    {
        $token = $this->repository->getById($event->tokenId);
        $token->obyomPrav = $event->obyomPrav;
        $token->proektySPushom = $event->proektySPushom;
        $token->proverenoV = $event->proverenoV;
        $this->repository->update($token);
    }
}
