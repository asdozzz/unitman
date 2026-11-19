<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Service;

use App\OpenCode\Business\Port\UmeetOpredelyatKanalUvedomleniyOpenCode;

/**
 * Единственное место, где формируется имя персонального канала уведомлений.
 *
 * Канал привязан к аккаунту и запечатан HMAC-SHA256 от секрета Centrifugo:
 * клиент получает готовое имя через API и не может подобрать канал чужого
 * аккаунта.
 */
final class KanalUvedomleniyOpenCode implements UmeetOpredelyatKanalUvedomleniyOpenCode
{
    public const CHANNEL_PREFIX = 'opencode_notifications#';

    private const HMAC_ALGORITHM = 'sha256';

    private const HMAC_PREFIX = 'opencode.notifications:';

    public function __construct(private readonly string $centrifugoSecret)
    {
    }

    public function kanalDlyaAccount(string $accountId): string
    {
        return self::CHANNEL_PREFIX
            . hash_hmac(self::HMAC_ALGORITHM, self::HMAC_PREFIX . $accountId, $this->centrifugoSecret);
    }
}
