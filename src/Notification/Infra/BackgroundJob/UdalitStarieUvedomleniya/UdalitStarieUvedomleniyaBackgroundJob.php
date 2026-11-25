<?php

declare(strict_types=1);

namespace App\Notification\Infra\BackgroundJob\UdalitStarieUvedomleniya;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\BackgroundJob\Infra\Service\BackgroundJobGroup;
use App\Notification\Business\Port\UvedomleniyaRepository;

final class UdalitStarieUvedomleniyaBackgroundJob extends AbstractBackgroundJob
{
    private const SECONDS_PER_DAY = 86400;

    public function __construct(
        private readonly UvedomleniyaRepository $uvedomleniya,
        private readonly string $infoRetentionDays,
        private readonly string $actionableRetentionDays,
    ) {
    }

    public function getName(): string
    {
        return 'udalit_starie_uvedomleniya';
    }

    public function getGroup(): BackgroundJobGroup
    {
        return BackgroundJobGroup::timers;
    }

    public function getDelay(): int
    {
        return 3600;
    }

    public function run(): bool
    {
        $now = time();
        $infoDays = max(1, (int) $this->infoRetentionDays);
        $actionableDays = max(1, (int) $this->actionableRetentionDays);

        $this->uvedomleniya->udalitStarie(
            false,
            $now - $infoDays * self::SECONDS_PER_DAY,
        );

        $this->uvedomleniya->udalitStarie(
            true,
            $now - $actionableDays * self::SECONDS_PER_DAY,
        );

        return true;
    }
}
