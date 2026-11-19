<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\OtmetitUvedomleniyaProchitannimi;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UvedomleniyaOpenCodeRepository;
use App\OpenCode\Business\UseCase\OtmetitUvedomleniyaProchitannimiUseCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Захват уведомлений: только реально прочитанные id своего аккаунта.
 */
final class OtmetitUvedomleniyaProchitannimiUseCaseTest extends TestCase
{
    private const ACCOUNT_ID = 'account-1';

    #[Test]
    public function vozvratit_tolko_realno_prochitannie_id_bez_dublei(): void
    {
        $repository = $this->createMock(UvedomleniyaOpenCodeRepository::class);
        $repository->expects($this->once())
            ->method('zahvatitNeprochitannie')
            ->with(
                self::ACCOUNT_ID,
                ['n-1', 'n-2'],
                $this->isType('int'),
            )
            ->willReturn(['n-1']);

        $ids = $this->useCase($repository)->handle(
            new OtmetitUvedomleniyaProchitannimi(['n-1', 'n-2', 'n-1']),
        );

        self::assertSame(['n-1'], $ids);
    }

    #[Test]
    public function pustoi_spisok_otklonyaetsya(): void
    {
        $repository = $this->createMock(UvedomleniyaOpenCodeRepository::class);
        $repository->expects($this->never())->method('zahvatitNeprochitannie');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.notification.ids_required');

        $this->useCase($repository)->handle(new OtmetitUvedomleniyaProchitannimi([]));
    }

    #[Test]
    public function prevyshenie_limita_otklonyaetsya(): void
    {
        $repository = $this->createMock(UvedomleniyaOpenCodeRepository::class);
        $repository->expects($this->never())->method('zahvatitNeprochitannie');

        $ids = [];

        for ($i = 0; $i < OtmetitUvedomleniyaProchitannimi::MAX_IDS + 1; ++$i) {
            $ids[] = 'n-' . $i;
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.notification.too_many_ids');

        $this->useCase($repository)->handle(new OtmetitUvedomleniyaProchitannimi($ids));
    }

    private function useCase(UvedomleniyaOpenCodeRepository $repository): OtmetitUvedomleniyaProchitannimiUseCase
    {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn(self::ACCOUNT_ID);

        return new OtmetitUvedomleniyaProchitannimiUseCase($repository, $credentials);
    }
}
