<?php

namespace App\Unitman\Business\ReadModel\Unit\WebhookEvent\WebhookEventPayload;

enum WebhookEventPayloadType: string
{
    case SOBRAN = 'build';
    case OBNOVLEN = 'update';
    case PODGOTOVLEN = 'prepare';
    case ZAPUSHEN = 'up';
    case OSTANOVLEN = 'down';
    case SBROSHENA_PODGOTOVKA = 'reset_prepare';

    case UDALEN = 'remove';
    case UDALEN_VRUCHNUYU = 'remove_manually';

}
