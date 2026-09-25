<?php

namespace App\Initialization\Business\UseCase;

use App\Initialization\Business\Command\ObnovitDefoltnyiProxyHost;
use App\Initialization\Business\Model\InitializationRecord;
use App\Initialization\Business\Port\InitializationRepository as InitializationRepositoryPort;
use App\Initialization\Business\Port\SecurityService;
use App\Initialization\Business\Port\UnitmanPort;

final class ObnovitDefoltnyiProxyHostUseCase
{
    public function __construct(
        private InitializationRepositoryPort $repository,
        private SecurityService $securityService
    ){}

    public function handle(ObnovitDefoltnyiProxyHost $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \DomainException('security.access_denied');
        }

        $record = $this->repository->getByProp('proxy_host');

        $record->value = $command->value;
        $record->init = 1;
        $this->repository->save($record);
    }
}
