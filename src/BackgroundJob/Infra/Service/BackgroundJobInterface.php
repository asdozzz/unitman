<?php

namespace App\BackgroundJob\Infra\Service;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.background_job')]
interface BackgroundJobInterface
{
    function getName(): string;

    function run(): bool;

    function getDelay(): int;
}
