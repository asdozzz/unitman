<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Adapter;

use App\Runner\Business\Port\RunnerRepository;
use App\Unitman\Business\Port\RunnerPublicAddressProvider;

/**
 * Реальная реализация `RunnerPublicAddressProvider` (multi-runner).
 *
 * Возвращает публичный адрес (`publicAddress`) раннера по его `runnerId` из
 * реестра раннеров. Ссылки строятся только по адресу, заданному
 * администратором. Default/fallback-адрес запрещён: если раннер не найден —
 * `runner.not_found`, если неактивен — `runner.inactive`, если
 * `publicAddress` не задан — `null` (вызывающая сторона обязана выдать явную
 * доменную ошибку, а не подставлять host).
 *
 * Контракт: plans/runner-multi-runner.md, §1 и §7.
 */
final class RunnerRepositoryPublicAddressProvider implements RunnerPublicAddressProvider
{
    public function __construct(private readonly RunnerRepository $runnerRepository)
    {
    }

    /**
     * @throws \DomainException `runner.not_found` | `runner.inactive`
     */
    public function getPublicAddress(string $runnerId): ?string
    {
        $runner = $this->runnerRepository->findById($runnerId);

        if ($runner === null) {
            throw new \DomainException('runner.not_found');
        }

        if (!$runner->isActive()) {
            throw new \DomainException('runner.inactive');
        }

        return $runner->getPublicAddress();
    }
}
