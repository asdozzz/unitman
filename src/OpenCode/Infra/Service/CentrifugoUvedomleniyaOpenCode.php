<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Service;

use App\OpenCode\Business\Port\UmeetOpredelyatKanalUvedomleniyOpenCode;
use App\OpenCode\Business\Port\UmeetPeredavatUvedomleniyaOpenCode;
use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;
use Fresh\CentrifugoBundle\Service\CentrifugoInterface;
use Psr\Log\LoggerInterface;

/**
 * Публикация уведомлений в Centrifugo.
 *
 * Ошибка публикации логируется и не пробрасывается: непрочитанное
 * уведомление остаётся в БД и будет отдано клиенту через HTTP API.
 */
final class CentrifugoUvedomleniyaOpenCode implements UmeetPeredavatUvedomleniyaOpenCode
{
    public function __construct(
        private readonly CentrifugoInterface $centrifugo,
        private readonly UmeetOpredelyatKanalUvedomleniyOpenCode $kanaly,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publish(UvedomlenieONovomSoobsheniiOpenCode $notification): void
    {
        try {
            $this->centrifugo->publish(
                $notification->toArray(),
                $this->kanaly->kanalDlyaAccount($notification->accountId),
            );
        } catch (\Throwable $error) {
            $this->logger->error('Не удалось опубликовать уведомление OpenCode.', [
                'notificationId' => $notification->id,
                'turnId' => $notification->turnId,
                'exception' => $error,
            ]);
        }
    }
}
