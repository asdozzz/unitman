<?php

namespace App\Unitman\Infra\Projection;

use App\Utils\EventSauce\CanProjectEvents;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('unitman.projection')]
interface UnitmanProjection extends CanProjectEvents
{

}
