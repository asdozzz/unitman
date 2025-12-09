<?php

namespace App\Unitman\Business\ReadModel\Unit\WebhookEvent;

use App\Unitman\Business\ReadModel\Unit\WebhookEvent\WebhookEventPayload\WebhookEventPayloadAccount;
use App\Unitman\Business\ReadModel\Unit\WebhookEvent\WebhookEventPayload\WebhookEventPayloadProject;
use App\Unitman\Business\ReadModel\Unit\WebhookEvent\WebhookEventPayload\WebhookEventPayloadType;
use App\Unitman\Business\ReadModel\Unit\WebhookEvent\WebhookEventPayload\WebhookEventPayloadUnit;

readonly class WebhookEventPayload
{
    public function __construct(
        public WebhookEventPayloadType $type,
        public WebhookEventPayloadAccount $author,
        public WebhookEventPayloadProject $project,
        public WebhookEventPayloadUnit $unit,
        public int $unixtime
    )
    {
    }

}
