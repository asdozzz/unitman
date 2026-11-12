<?php

declare(strict_types=1);

namespace App\BackgroundJob\Infra\Service;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.background_job')]
interface BackgroundJobInterface
{
    /** @return non-empty-string */
    public function getName(): string;

    public function run(): bool;

    /**
     * @return int<0, max>
     * */
    public function getDelay(): int;

    /**
     * @return int<1, 20>
     * */
    public function getProcessNum(): int;
}
