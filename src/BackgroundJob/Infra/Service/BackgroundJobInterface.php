<?php

namespace App\BackgroundJob\Infra\Service;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.background_job')]
interface BackgroundJobInterface
{
    /** @return non-empty-string */
    function getName(): string;

    function run(): bool;

    /**
     * @return int<0, max>
     * */
    function getDelay(): int;
}
