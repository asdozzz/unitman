<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Infra\Repository;

use App\Account\Api\AccountApi;
use App\AiChat\Business\Model\FiltrSessiyAiChata;
use App\AiChat\Business\Model\SessiyaAiChata;
use App\AiChat\Business\Model\SoobshenieAiChata;
use App\AiChat\Infra\Repository\SqlSessiyaAiChataRepository;
use App\AiChat\Infra\Repository\SqlSoobshenieAiChataRepository;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use PHPUnit\Framework\Attributes\Test;

/**
 * Список сессий в БД: пустые скрыты, архивные фильтруются, превью ровно одно,
 * поиск по заголовку/первому сообщению и пагинация с total.
 */
final class SqlSessiyaAiChataRepositoryTest extends AbstractTestCaseWithTransactionWrapper
{
    private const NOW = 1_700_000_000;

    private function repository(): SqlSessiyaAiChataRepository
    {
        \assert(self::$db !== null);
        \assert(self::$container !== null);

        return new SqlSessiyaAiChataRepository(
            self::$db,
            self::$container->get(AccountApi::class),
        );
    }

    private function messages(): SqlSoobshenieAiChataRepository
    {
        \assert(self::$db !== null);

        return new SqlSoobshenieAiChataRepository(self::$db);
    }

    #[Test]
    public function spisok_skryvaet_pustye_i_otdaet_odno_preview(): void
    {
        $accountId = 'acc-list-' . uniqid();

        $this->repository()->sohranit($this->sessiya('s1-' . $accountId, $accountId, 'Alpha', self::NOW + 30));
        $this->repository()->sohranit($this->sessiya('s2-' . $accountId, $accountId, 'Empty', self::NOW + 20));
        $this->messages()->dobavit(
            SoobshenieAiChata::polzovatelya('m1-' . $accountId, 's1-' . $accountId, 1, '  Hello world  ', self::NOW),
            SoobshenieAiChata::assistenta('m2-' . $accountId, 's1-' . $accountId, 2, 'second', [], self::NOW),
        );

        $result = $this->repository()->spisok($accountId, new FiltrSessiyAiChata());

        $this->assertSame(1, $result->total);
        $this->assertCount(1, $result->items);
        $this->assertSame('s1-' . $accountId, $result->items[0]->id);
        $this->assertSame('Hello world', $result->items[0]->preview, 'превью — первое сообщение');
        $this->assertSame(2, $result->items[0]->messageCount);
        $this->assertFalse($result->hasMore());
    }

    #[Test]
    public function spisok_filtruet_arhivnye_po_umolchaniyu_i_vklyuchaet_ih_po_flag(): void
    {
        $accountId = 'acc-arch-' . uniqid();

        $this->repository()->sohranit($this->sessiya('active-' . $accountId, $accountId, 'Active', self::NOW + 30));
        $this->repository()->sohranit($this->sessiya(
            'archived-' . $accountId,
            $accountId,
            'Archived',
            self::NOW + 10,
            self::NOW + 10,
        ));
        $this->messages()->dobavit(
            SoobshenieAiChata::polzovatelya('ma-' . $accountId, 'active-' . $accountId, 1, 'active-msg', self::NOW),
            SoobshenieAiChata::polzovatelya('mr-' . $accountId, 'archived-' . $accountId, 1, 'archived-msg', self::NOW),
        );

        $bezArhivnyh = $this->repository()->spisok($accountId, new FiltrSessiyAiChata());
        $this->assertSame(1, $bezArhivnyh->total);
        $this->assertSame('active-' . $accountId, $bezArhivnyh->items[0]->id);

        $sArhivnymi = $this->repository()->spisok($accountId, new FiltrSessiyAiChata(includeArchived: true));
        $this->assertSame(2, $sArhivnymi->total);
        $this->assertSame(
            ['active-' . $accountId, 'archived-' . $accountId],
            array_map(static fn ($item): string => $item->id, $sArhivnymi->items),
            'сортировка по updated_at desc',
        );
    }

    #[Test]
    public function poisk_ishchet_po_zagolovku_i_tekstu_pervogo_soobsheniya(): void
    {
        $accountId = 'acc-search-' . uniqid();

        $this->repository()->sohranit($this->sessiya('title-' . $accountId, $accountId, 'Needle in title', self::NOW + 20));
        $this->repository()->sohranit($this->sessiya('body-' . $accountId, $accountId, 'Other', self::NOW + 10));
        $this->messages()->dobavit(
            SoobshenieAiChata::polzovatelya('mt-' . $accountId, 'title-' . $accountId, 1, 'title-body', self::NOW),
            SoobshenieAiChata::polzovatelya('mb-' . $accountId, 'body-' . $accountId, 1, 'Needle in message', self::NOW),
        );

        $poZagolovku = $this->repository()->spisok(
            $accountId,
            new FiltrSessiyAiChata(search: 'Needle in title'),
        );
        $this->assertSame(['title-' . $accountId], array_map(static fn ($i): string => $i->id, $poZagolovku->items));

        $poSoobsheniyu = $this->repository()->spisok(
            $accountId,
            new FiltrSessiyAiChata(search: 'Needle in message'),
        );
        $this->assertSame(['body-' . $accountId], array_map(static fn ($i): string => $i->id, $poSoobsheniyu->items));

        $pusto = $this->repository()->spisok($accountId, new FiltrSessiyAiChata(search: 'no-such-text'));
        $this->assertSame(0, $pusto->total);
        $this->assertSame([], $pusto->items);
    }

    #[Test]
    public function paginaciya_vozvrashchaet_total_i_has_more(): void
    {
        $accountId = 'acc-page-' . uniqid();

        foreach ([1, 2, 3] as $n) {
            $this->repository()->sohranit($this->sessiya(
                'p' . $n . '-' . $accountId,
                $accountId,
                'Session ' . $n,
                self::NOW + $n * 10,
            ));
            $this->messages()->dobavit(
                SoobshenieAiChata::polzovatelya('pm' . $n . '-' . $accountId, 'p' . $n . '-' . $accountId, 1, 'm' . $n, self::NOW),
            );
        }

        $pervaya = $this->repository()->spisok($accountId, new FiltrSessiyAiChata(limit: 2, offset: 0));
        $this->assertSame(3, $pervaya->total);
        $this->assertCount(2, $pervaya->items);
        $this->assertSame(['p3-' . $accountId, 'p2-' . $accountId], array_map(static fn ($i): string => $i->id, $pervaya->items));
        $this->assertTrue($pervaya->hasMore());

        $vtoraya = $this->repository()->spisok($accountId, new FiltrSessiyAiChata(limit: 2, offset: 2));
        $this->assertSame(3, $vtoraya->total);
        $this->assertSame(['p1-' . $accountId], array_map(static fn ($i): string => $i->id, $vtoraya->items));
        $this->assertFalse($vtoraya->hasMore());
    }

    #[Test]
    public function udalenie_sessii_kaskadno_udaliyaet_soobsheniya(): void
    {
        $accountId = 'acc-del-' . uniqid();
        $sessiya = $this->sessiya('d1-' . $accountId, $accountId, 'To delete', self::NOW, self::NOW);

        $this->repository()->sohranit($sessiya);
        $this->messages()->dobavit(
            SoobshenieAiChata::polzovatelya('dm1-' . $accountId, 'd1-' . $accountId, 1, 'bye', self::NOW),
        );

        $this->repository()->udalit($sessiya);

        $this->assertSame(0, $this->messages()->kolichestvo($sessiya->id()));
        $this->assertNull($this->repository()->naytiPoId($sessiya->id()));
    }

    private function sessiya(
        string $id,
        string $accountId,
        string $title,
        int $updatedAt,
        ?int $archivedAt = null,
    ): SessiyaAiChata {
        return SessiyaAiChata::restore(
            $id,
            $accountId,
            $title,
            'openai',
            null,
            'gpt-4o-mini',
            null,
            null,
            self::NOW + 86_400,
            $archivedAt,
            $archivedAt !== null ? 'manual' : null,
            self::NOW,
            $updatedAt,
        );
    }
}
