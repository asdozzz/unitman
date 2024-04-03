<?php

namespace App\BackgroundJob\Infra\Service;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.background_job')]
interface BackgroundJobInterface
{
    function getWorkflowClass(): string;
    function getMethodName(): string;
    function getName(): string;
}
