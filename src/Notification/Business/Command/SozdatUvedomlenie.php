<?php

declare(strict_types=1);

namespace App\Notification\Business\Command;

use App\Notification\Business\Model\TipUvedomleniya;
use App\Notification\Business\Model\UrovenVazhnosti;

/**
 * Внутренняя команда создания уведомления для набора получателей.
 */
final class SozdatUvedomlenie
{
    /**
     * @param list<string>          $recipientIds
     * @param array<string, mixed>  $payload
     */
    public function __construct(
        public readonly TipUvedomleniya $type,
        public readonly UrovenVazhnosti $severity,
        public readonly array $recipientIds,
        public readonly array $payload,
        public readonly ?string $dedupKey,
        public readonly ?int $createdAt = null,
    ) {
    }
}
