<?php

namespace App\Unitman\Infra\Projection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('project.sync_unit')]
interface SyncProjectionForUnit
{

}
