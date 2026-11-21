<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Projection;

use App\Utils\EventSauce\CanProjectEvents;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('unitman.lichniy_token.projection')]
interface LichniyTokenProjection extends CanProjectEvents
{
}
