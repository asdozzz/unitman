<?php

declare(strict_types=1);

namespace App\Notification\Tests\Business\UseCase;

use App\Notification\Business\Command\IzmenitNastroikuUvedomleniya;
use App\Notification\Business\Model\TipUvedomleniya;
use App\Notification\Business\Port\NastroikiUvedomleniyRepository;
use App\Notification\Business\Port\UmeetOpredelyatPolzovatelya;
use App\Notification\Business\UseCase\IzmenitNastroikuUvedomleniyaUseCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IzmenitNastroikuUvedomleniyaUseCaseTest extends TestCase
{
    #[Test]
    public function sistemniy_tip_nelzya_otklyuchit(): void
    {
        $nastroiki = new FakeNastroikiUvedomleniyRepository();
        $useCase = new IzmenitNastroikuUvedomleniyaUseCase(
            $nastroiki,
            new FakeOpredeleniePolzovatelya('account-1'),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('notification.preference.system_type');

        $useCase->handle(new IzmenitNastroikuUvedomleniya(TipUvedomleniya::RUNNER_THREAT, false));
    }

    #[Test]
    public function sistemniy_tip_ne_sohranyaet_nastroyku(): void
    {
        $nastroiki = new FakeNastroikiUvedomleniyRepository();
        $useCase = new IzmenitNastroikuUvedomleniyaUseCase(
            $nastroiki,
            new FakeOpredeleniePolzovatelya('account-1'),
        );

        try {
            $useCase->handle(new IzmenitNastroikuUvedomleniya(TipUvedomleniya::RUNNER_THREAT, false));
            self::fail('Ожидалось исключение для системного типа');
        } catch (\DomainException) {
            // ожидаемо
        }

        self::assertSame([], $nastroiki->sohraneno);
    }

    #[Test]
    public function nesisterniy_tip_sohranyaet_nastroyku_dlya_tekushego_polzovatelya(): void
    {
        $nastroiki = new FakeNastroikiUvedomleniyRepository();
        $useCase = new IzmenitNastroikuUvedomleniyaUseCase(
            $nastroiki,
            new FakeOpredeleniePolzovatelya('account-42'),
        );

        $useCase->handle(new IzmenitNastroikuUvedomleniya(TipUvedomleniya::UNIT_STARTED, false));

        self::assertCount(1, $nastroiki->sohraneno);
        self::assertSame('account-42', $nastroiki->sohraneno[0]['accountId']);
        self::assertSame(TipUvedomleniya::UNIT_STARTED, $nastroiki->sohraneno[0]['type']);
        self::assertFalse($nastroiki->sohraneno[0]['enabled']);
    }
}

final class FakeOpredeleniePolzovatelya implements UmeetOpredelyatPolzovatelya
{
    public function __construct(private string $accountId)
    {
    }

    public function tekushiyAccountId(): string
    {
        return $this->accountId;
    }

    public function isAdmin(): bool
    {
        return false;
    }
}

final class FakeNastroikiUvedomleniyRepository implements NastroikiUvedomleniyRepository
{
    /** @var list<array{accountId: string, type: TipUvedomleniya, enabled: bool}> */
    public array $sohraneno = [];

    public function findDlyaAccounta(string $accountId): array
    {
        return [];
    }

    public function findOtkluchennih(TipUvedomleniya $type, array $accountIds): array
    {
        return [];
    }

    public function save(string $accountId, TipUvedomleniya $type, bool $enabled, int $now): void
    {
        $this->sohraneno[] = [
            'accountId' => $accountId,
            'type' => $type,
            'enabled' => $enabled,
        ];
    }
}
