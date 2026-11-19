<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitUvedomleniyaOpenCode;
use App\OpenCode\Business\Port\UmeetOpredelyatKanalUvedomleniyOpenCode;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UvedomleniyaOpenCodeRepository;
use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;
use App\OpenCode\Business\UseCase\PoluchitUvedomleniyaOpenCodeQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Query непрочитанных уведомлений: канал текущего аккаунта и фильтрация в БД.
 */
final class PoluchitUvedomleniyaOpenCodeQueryTest extends TestCase
{
    private const ACCOUNT_ID = 'account-1';
    private const CHANNEL = 'opencode_notifications#abc';

    #[Test]
    public function vozvratit_kanal_i_neprochitannie_svoego_akkaunta(): void
    {
        $repository = $this->createMock(UvedomleniyaOpenCodeRepository::class);
        $repository->expects($this->once())
            ->method('findNeprochitannie')
            ->with(self::ACCOUNT_ID, PoluchitUvedomleniyaOpenCode::DEFAULT_LIMIT)
            ->willReturn([$this->uvedomlenie()]);

        $readModel = $this->query($repository)->handle(new PoluchitUvedomleniyaOpenCode());

        $data = $readModel->toArray();

        self::assertSame(self::CHANNEL, $data['channel']);
        self::assertCount(1, $data['notifications']);
        self::assertSame('n-1', $data['notifications'][0]['id']);
        self::assertSame('t-1', $data['notifications'][0]['turnId']);
        self::assertArrayNotHasKey('accountId', $data['notifications'][0]);
    }

    #[Test]
    public function limit_ogranichivaetsya_sverhu_sta(): void
    {
        $repository = $this->createMock(UvedomleniyaOpenCodeRepository::class);
        $repository->expects($this->once())
            ->method('findNeprochitannie')
            ->with(self::ACCOUNT_ID, PoluchitUvedomleniyaOpenCode::MAX_LIMIT)
            ->willReturn([]);

        $this->query($repository)->handle(new PoluchitUvedomleniyaOpenCode(500));
    }

    #[Test]
    public function nepolozhitelniy_limit_zamenyaetsya_znacheniem_po_umolchaniyu(): void
    {
        $repository = $this->createMock(UvedomleniyaOpenCodeRepository::class);
        $repository->expects($this->once())
            ->method('findNeprochitannie')
            ->with(self::ACCOUNT_ID, PoluchitUvedomleniyaOpenCode::DEFAULT_LIMIT)
            ->willReturn([]);

        $this->query($repository)->handle(new PoluchitUvedomleniyaOpenCode(0));
    }

    private function query(UvedomleniyaOpenCodeRepository $repository): PoluchitUvedomleniyaOpenCodeQuery
    {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn(self::ACCOUNT_ID);

        $kanaly = $this->createMock(UmeetOpredelyatKanalUvedomleniyOpenCode::class);
        $kanaly->method('kanalDlyaAccount')->willReturn(self::CHANNEL);

        return new PoluchitUvedomleniyaOpenCodeQuery($repository, $credentials, $kanaly);
    }

    private function uvedomlenie(): UvedomlenieONovomSoobsheniiOpenCode
    {
        return new UvedomlenieONovomSoobsheniiOpenCode(
            'n-1',
            self::ACCOUNT_ID,
            's-1',
            't-1',
            'Сессия',
            'ANSWERED',
            'ANSWER',
            1_700_000_000,
        );
    }
}
