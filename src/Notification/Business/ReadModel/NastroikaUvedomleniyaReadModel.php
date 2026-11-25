<?php

declare(strict_types=1);

namespace App\Notification\Business\ReadModel;

use App\Notification\Business\Model\TipUvedomleniya;
use App\Notification\Business\Model\UrovenVazhnosti;

/**
 * Описание типа уведомления и персональной настройки.
 */
final class NastroikaUvedomleniyaReadModel
{
    public function __construct(
        public readonly TipUvedomleniya $type,
        public readonly bool $enabled,
        public readonly UrovenVazhnosti $severity,
        public readonly bool $adminOnly,
        public readonly bool $system,
        public readonly bool $canDisable,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'enabled' => $this->enabled,
            'severity' => $this->severity->value,
            'adminOnly' => $this->adminOnly,
            'system' => $this->system,
            'canDisable' => $this->canDisable,
        ];
    }
}
