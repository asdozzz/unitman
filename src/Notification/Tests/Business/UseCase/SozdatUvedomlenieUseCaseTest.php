<?php

declare(strict_types=1);

namespace App\Notification\Tests\Business\UseCase;

use App\Notification\Business\Command\SozdatUvedomlenie;
use App\Notification\Business\Model\TipUvedomleniya;
use App\Notification\Business\Model\UrovenVazhnosti;
use App\Notification\Business\Model\Uvedomlenie;
use App\Notification\Business\Port\CanGenerateGuid;
use App\Notification\Business\Port\NastroikiUvedomleniyRepository;
use App\Notification\Business\Port\UvedomleniyaRepository;
use App\Notification\Business\ReadModel\UvedomlenieReadModel;
use App\Notification\Business\UseCase\SozdatUvedomlenieUseCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SozdatUvedomlenieUseCaseTest extends TestCase
{
    #[Test]
    public function sistemniy_tip_ignoriruet_nastroiki_i_sozdaet_uvedomlenie(): void
    {
        $repo = new FakeUvedomleniyaRepository();
        $nastroiki = new FakeNastroikiRepository(['admin-1' => false]);

        $this->useCase($repo, $nastroiki)->handle(new SozdatUvedomlenie(
            TipUvedomleniya::RUNNER_THREAT,
            UrovenVazhnosti::CRITICAL,
            ['admin-1'],
            ['runnerId' => 'r-1'],
            'runner_threat:r-1:critical:1',
        ));

        self::assertNotNull($repo->sokhraneno);
        self::assertSame(['admin-1'], $repo->poluchateli);
        self::assertTrue($repo->sokhraneno->requiresRead());
    }

    #[Test]
    public function nesisterniy_tip_otbrasyvaet_otkluchivshih(): void
    {
        $repo = new FakeUvedomleniyaRepository();
        $nastroiki = new FakeNastroikiRepository(['user-2' => false]);

        $this->useCase($repo, $nastroiki)->handle(new SozdatUvedomlenie(
            TipUvedomleniya::UNIT_STARTED,
            UrovenVazhnosti::INFO,
            ['user-1', 'user-2'],
            ['unitId' => 'u-1'],
            'unit_started:u-1:j-1',
        ));

        self::assertNotNull($repo->sokhraneno);
        self::assertSame(['user-1'], $repo->poluchateli);
        self::assertFalse($repo->sokhraneno->requiresRead());
    }

    #[Test]
    public function esli_vse_poluchateli_otkluchili_tip_uvedomlenie_ne_sozdaetsya(): void
    {
        $repo = new FakeUvedomleniyaRepository();
        $nastroiki = new FakeNastroikiRepository(['user-1' => false]);

        $this->useCase($repo, $nastroiki)->handle(new SozdatUvedomlenie(
            TipUvedomleniya::UNIT_STARTED,
            UrovenVazhnosti::INFO,
            ['user-1'],
            ['unitId' => 'u-1'],
            'unit_started:u-1:j-1',
        ));

        self::assertNull($repo->sokhraneno);
    }

    #[Test]
    public function povtorniy_dedup_key_ne_sozdaet_dubl(): void
    {
        $repo = new FakeUvedomleniyaRepository();
        $nastroiki = new FakeNastroikiRepository();
        $useCase = $this->useCase($repo, $nastroiki);

        $command = new SozdatUvedomlenie(
            TipUvedomleniya::RUNNER_THREAT,
            UrovenVazhnosti::CRITICAL,
            ['admin-1'],
            ['runnerId' => 'r-1'],
            'runner_threat:r-1:critical:1',
        );

        $useCase->handle($command);
        $useCase->handle($command);

        self::assertSame(1, $repo->sozdano);
    }

    private function useCase(
        FakeUvedomleniyaRepository $repo,
        FakeNastroikiRepository $nastroiki,
    ): SozdatUvedomlenieUseCase {
        return new SozdatUvedomlenieUseCase($repo, $nastroiki, new FakeGuidGenerator());
    }
}

final class FakeGuidGenerator implements CanGenerateGuid
{
    private int $schetchik = 0;

    public function makeGuid(): string
    {
        $this->schetchik += 1;

        return 'notification-' . $this->schetchik;
    }
}

final class FakeNastroikiRepository implements NastroikiUvedomleniyRepository
{
    /**
     * @param array<string, bool> $otklucheni
     */
    public function __construct(private array $otklucheni = [])
    {
    }

    public function findDlyaAccounta(string $accountId): array
    {
        return [];
    }

    public function findOtkluchennih(TipUvedomleniya $type, array $accountIds): array
    {
        return array_values(array_filter(
            $accountIds,
            fn (string $accountId): bool => ($this->otklucheni[$accountId] ?? true) === false,
        ));
    }

    public function save(string $accountId, TipUvedomleniya $type, bool $enabled, int $now): void
    {
    }
}

final class FakeUvedomleniyaRepository implements UvedomleniyaRepository
{
    public ?Uvedomlenie $sokhraneno = null;

    /** @var list<string> */
    public array $poluchateli = [];

    public int $sozdano = 0;

    /** @var array<string, Uvedomlenie> */
    private array $poDedup = [];

    public function save(Uvedomlenie $uvedomlenie, array $recipientIds, string $channel): void
    {
        $this->sokhraneno = $uvedomlenie;
        $this->poluchateli = $recipientIds;
        $this->sozdano += 1;

        if ($uvedomlenie->getDedupKey() !== null) {
            $this->poDedup[$uvedomlenie->getDedupKey()] = $uvedomlenie;
        }
    }

    public function findByDedupKey(string $dedupKey): ?Uvedomlenie
    {
        return $this->poDedup[$dedupKey] ?? null;
    }

    public function findById(string $id): ?Uvedomlenie
    {
        return null;
    }

    public function findDlyaPolzovatelya(string $accountId, int $limit, bool $onlyActionable): array
    {
        return [];
    }

    public function schetchikNeprochitannih(string $accountId): int
    {
        return 0;
    }

    public function estPoluchatelem(string $notificationId, string $accountId): bool
    {
        return false;
    }

    public function otmetitProchitannim(string $notificationId, string $accountId, int $now): bool
    {
        return false;
    }

    public function otmetitVseProchitannimi(string $accountId, int $now): int
    {
        return 0;
    }

    public function udalitStarie(bool $requiresRead, int $createdBefore): int
    {
        return 0;
    }

    public function findPoluchateleyIds(string $notificationId): array
    {
        return [];
    }
}
