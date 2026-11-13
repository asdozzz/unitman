<?php

declare(strict_types=1);

namespace App\Unitman\Business\Utils;

enum QueueNameEnum: string
{
    case SOBITIE_IZ_HRANILISHA = 'sobitiya_iz_hranilisha';
    case WEBHOOK_EVENT = 'webhook_event';
    case WEBSOCKET_UNIT_EVENT = 'websocket_unit_event';
    case WEBSOCKET_PROJECT_EVENT = 'websocket_project_event';
}
