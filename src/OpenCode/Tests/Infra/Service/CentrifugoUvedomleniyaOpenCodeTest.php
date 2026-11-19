<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\Service;

use App\OpenCode\Business\Port\UmeetOpredelyatKanalUvedomleniyOpenCode;
use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;
use App\OpenCode\Infra\Service\CentrifugoUvedomleniyaOpenCode;
use Fresh\CentrifugoBundle\Service\CentrifugoInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CentrifugoUvedomleniyaOpenCodeTest extends TestCase
{
    private const CHANNEL = 'opencode_notifications#abc';

    #[Test]
    public function publikuet_payload_v_kanal_akkaunta(): void
    {
        $notification = $this->uvedomlenie();

        $kanaly = $this->createMock(UmeetOpredelyatKanalUvedomleniyOpenCode::class);
        $kanaly->expects($this->once())
            ->method('kanalDlyaAccount')
            ->with('acc-1')
            ->willReturn(self::CHANNEL);

        $centrifugo = $this->createMock(CentrifugoInterface::class);
        $centrifugo->expects($this->once())
            ->method('publish')
            ->with($notification->toArray(), self::CHANNEL);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        (new CentrifugoUvedomleniyaOpenCode($centrifugo, $kanaly, $logger))->publish($notification);
    }

    #[Test]
    public function oshibka_publikacii_logiruetsya_i_ne_probrasyvaetsya(): void
    {
        $notification = $this->uvedomlenie();

        $kanaly = $this->createMock(UmeetOpredelyatKanalUvedomleniyOpenCode::class);
        $kanaly->method('kanalDlyaAccount')->willReturn(self::CHANNEL);

        $centrifugo = $this->createMock(CentrifugoInterface::class);
        $centrifugo->method('publish')->willThrowException(new \RuntimeException('centrifugo down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        (new CentrifugoUvedomleniyaOpenCode($centrifugo, $kanaly, $logger))->publish($notification);
    }

    private function uvedomlenie(): UvedomlenieONovomSoobsheniiOpenCode
    {
        return new UvedomlenieONovomSoobsheniiOpenCode(
            'n-1',
            'acc-1',
            's-1',
            't-1',
            'Сессия',
            'ANSWERED',
            'ANSWER',
            1_700_000_000,
        );
    }
}
