<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitSpisokSessiy;
use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCodeFilter;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\UseCase\PoluchitSpisokSessiyQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Список сессий всегда ограничен владельцем: идентификатор аккаунта берётся
 * из реквизитов запроса, а не из параметров клиента, поэтому чужую сессию
 * этим эндпоинтом получить нельзя.
 *
 * Фильтры собираются из query-параметров: по умолчанию показываются все сессии
 * (архив — состояние, а не удаление), поиск идёт по названию через LIKE.
 *
 * Тест чистый (без БД): оба порта — интерфейсы, поведение определяется моками.
 */
final class PoluchitSpisokSessiyQueryTest extends TestCase
{
    private const NOW = 1_700_000_000;

    private const ACCOUNT_ID = 'acc-1';

    #[Test]
    public function vladeles_sessiy_beretsya_iz_rekvizitov(): void
    {
        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->expects($this->once())
            ->method('findByAccountId')
            ->with(self::ACCOUNT_ID, $this->isInstanceOf(SessiyaOpenCodeFilter::class))
            ->willReturn([]);

        $query = new PoluchitSpisokSessiyQuery($repository, $this->rekvizity(null));

        $this->assertSame([], $query->handle(new PoluchitSpisokSessiy()));
    }

    #[Test]
    public function po_umolchaniyu_pokazivayutsya_vse_sessii_bez_poiska(): void
    {
        $filter = $this->poluchitFiltr(new PoluchitSpisokSessiy());

        $this->assertFalse($filter->isOnlyActive());
        $this->assertNull($filter->getSearch());
        $this->assertFalse($filter->estPoisk());
    }

    #[Test]
    public function tolko_aktivnie_prokinutsya_v_filtr(): void
    {
        $filter = $this->poluchitFiltr(new PoluchitSpisokSessiy(true));

        $this->assertTrue($filter->isOnlyActive());
        $this->assertNull($filter->getSearch());
    }

    #[Test]
    public function poisk_oberezaetsya_ot_probelnih_simvolov(): void
    {
        $filter = $this->poluchitFiltr(new PoluchitSpisokSessiy(false, '  release  '));

        $this->assertSame('release', $filter->getSearch());
        $this->assertTrue($filter->estPoisk());
    }

    #[Test]
    public function poisk_iz_odnih_probelov_ignoriruetsya(): void
    {
        $filter = $this->poluchitFiltr(new PoluchitSpisokSessiy(false, '   '));

        $this->assertNull($filter->getSearch());
        $this->assertFalse($filter->estPoisk());
    }

    #[Test]
    public function poisk_ogranichen_dlinnoi_stroki(): void
    {
        $filter = $this->poluchitFiltr(new PoluchitSpisokSessiy(false, str_repeat('a', 300)));

        $this->assertNotNull($filter->getSearch());
        $this->assertSame(256, mb_strlen((string) $filter->getSearch()));
    }

    #[Test]
    public function sessii_otdavayutsya_read_modelami(): void
    {
        $aktivnaya = SessiyaOpenCode::create(
            's-1',
            self::ACCOUNT_ID,
            'cred-1',
            'Диалог по релизу',
            [$this->privyazka('unit-1', 'project-1')],
            self::NOW,
        );

        $arhivnaya = SessiyaOpenCode::create(
            's-2',
            self::ACCOUNT_ID,
            'cred-1',
            'Старый диалог',
            [$this->privyazka('unit-2', 'project-2')],
            self::NOW,
        );
        $arhivnaya->arhivirovat(SessiyaOpenCode::ARCHIVE_REASON_UNIT_DELETED, self::NOW + 10);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->expects($this->once())
            ->method('findByAccountId')
            ->willReturn([$aktivnaya, $arhivnaya]);

        $query = new PoluchitSpisokSessiyQuery($repository, $this->rekvizity(null));

        $sessions = $query->handle(new PoluchitSpisokSessiy());

        $this->assertCount(2, $sessions);

        $this->assertSame('s-1', $sessions[0]->id);
        $this->assertSame('cred-1', $sessions[0]->credentialId);
        $this->assertSame('Диалог по релизу', $sessions[0]->title);
        $this->assertNull($sessions[0]->externalSessionId);
        $this->assertCount(1, $sessions[0]->units);
        $this->assertSame('unit-1', $sessions[0]->units[0]->unitId);
        $this->assertSame('project-1', $sessions[0]->units[0]->unitProjectId);
        $this->assertSame('unit-1', $sessions[0]->unitId);
        $this->assertSame('project-1', $sessions[0]->unitProjectId);
        $this->assertFalse($sessions[0]->archived);
        $this->assertNull($sessions[0]->archiveReason);
        $this->assertSame(self::NOW, $sessions[0]->createdAt);
        $this->assertSame(self::NOW, $sessions[0]->updatedAt);

        $this->assertSame('s-2', $sessions[1]->id);
        $this->assertSame('unit-2', $sessions[1]->unitId);
        $this->assertTrue($sessions[1]->archived);
        $this->assertSame(SessiyaOpenCode::ARCHIVE_REASON_UNIT_DELETED, $sessions[1]->archiveReason);
        $this->assertSame(self::NOW + 10, $sessions[1]->updatedAt);
    }

    #[Test]
    public function neskolko_unitov_otdavutsya_v_poryadke_privezki(): void
    {
        $session = SessiyaOpenCode::create(
            's-4',
            self::ACCOUNT_ID,
            'cred-1',
            'Multi-root',
            [
                $this->privyazka('unit-1', 'project-1'),
                $this->privyazka('unit-2', 'project-2'),
                $this->privyazka('unit-3', 'project-3'),
            ],
            self::NOW,
        );

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->expects($this->once())
            ->method('findByAccountId')
            ->willReturn([$session]);

        $query = new PoluchitSpisokSessiyQuery($repository, $this->rekvizity(null));

        $sessions = $query->handle(new PoluchitSpisokSessiy());

        $this->assertCount(3, $sessions[0]->units);
        $this->assertSame(
            ['unit-1', 'unit-2', 'unit-3'],
            array_map(
                static fn ($privyazka): string => $privyazka->unitId,
                $sessions[0]->units,
            ),
        );
        $this->assertSame('unit-1', $sessions[0]->unitId);
    }

    #[Test]
    public function svobodnaya_sessiya_ne_imeet_unita(): void
    {
        $svobodnaya = SessiyaOpenCode::create('s-3', self::ACCOUNT_ID, 'cred-1', null, [], self::NOW);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->expects($this->once())
            ->method('findByAccountId')
            ->willReturn([$svobodnaya]);

        $query = new PoluchitSpisokSessiyQuery($repository, $this->rekvizity(null));

        $sessions = $query->handle(new PoluchitSpisokSessiy());

        $this->assertCount(1, $sessions);
        $this->assertSame([], $sessions[0]->units);
        $this->assertNull($sessions[0]->unitId);
        $this->assertNull($sessions[0]->unitProjectId);
        $this->assertFalse($sessions[0]->archived);
        $this->assertSame('', $sessions[0]->title);
    }

    private function privyazka(string $unitId, string $unitProjectId): PrivyazkaKUnitu
    {
        return PrivyazkaKUnitu::izZnacheniy($unitId, $unitProjectId);
    }

    private function poluchitFiltr(PoluchitSpisokSessiy $command): SessiyaOpenCodeFilter
    {
        $captured = null;

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->expects($this->once())
            ->method('findByAccountId')
            ->willReturnCallback(
                static function (string $accountId, SessiyaOpenCodeFilter $filter) use (&$captured): array {
                    $captured = $filter;

                    return [];
                },
            );

        $query = new PoluchitSpisokSessiyQuery($repository, $this->rekvizity(null));
        $query->handle($command);

        $this->assertInstanceOf(SessiyaOpenCodeFilter::class, $captured);

        return $captured;
    }

    private function rekvizity(?string $capturedAccountId): UmeetProveryatRekvizityOpenCode
    {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn(self::ACCOUNT_ID);

        return $credentials;
    }
}