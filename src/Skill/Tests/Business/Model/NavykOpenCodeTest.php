<?php

declare(strict_types=1);

namespace App\Skill\Tests\Business\Model;

use App\Skill\Business\Model\NavykOpenCode;
use App\Skill\Business\Utils\ProverkaNavyka;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Доменные инварианты навыка: начальная версия и хэш, рост версии только при
 * изменении содержимого, нормализация проектов и мягкое архивирование.
 */
final class NavykOpenCodeTest extends TestCase
{
    private const NOW = 1_700_000_000;

    /**
     * @param list<string> $projectIds
     */
    private function sozdat(array $projectIds = [], bool $isGlobal = true): NavykOpenCode
    {
        return NavykOpenCode::create(
            'skill-1',
            'my-skill',
            'Мой навык',
            'Описание навыка',
            'Тело навыка',
            $isGlobal,
            $projectIds,
            'account-1',
            self::NOW,
        );
    }

    #[Test]
    public function sozdanie_zapolnyaet_versiyu_hash_i_avtorov(): void
    {
        $navyk = $this->sozdat();

        $this->assertSame(1, $navyk->getVersion());
        $this->assertSame(
            ProverkaNavyka::hashKontenta('Мой навык', 'Описание навыка', 'Тело навыка'),
            $navyk->getContentHash(),
        );
        $this->assertSame('account-1', $navyk->getCreatedBy());
        $this->assertSame('account-1', $navyk->getUpdatedBy());
        $this->assertSame(self::NOW, $navyk->getCreatedAt());
        $this->assertSame(self::NOW, $navyk->getUpdatedAt());
        $this->assertTrue($navyk->isGlobal());
        $this->assertFalse($navyk->isArchived());
        $this->assertNull($navyk->getArchivedAt());
    }

    #[Test]
    public function proekty_normalizuyutsya_pri_sozdanii(): void
    {
        $navyk = $this->sozdat([' p-1 ', 'p-2', 'p-1'], false);

        $this->assertSame(['p-1', 'p-2'], $navyk->getProjectIds());
        $this->assertFalse($navyk->isGlobal());
    }

    #[Test]
    public function proektnyi_navyk_bez_proektov_zapreshchen(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.project.required');

        $this->sozdat([], false);
    }

    #[Test]
    public function izmenenie_soderzhimogo_podnimaet_versiyu_i_menyaet_hash(): void
    {
        $navyk = $this->sozdat();
        $staryiHash = $navyk->getContentHash();

        $izmenilos = $navyk->izmenit('account-2', self::NOW + 10, content: 'Новое тело');

        $this->assertTrue($izmenilos);
        $this->assertSame(2, $navyk->getVersion());
        $this->assertNotSame($staryiHash, $navyk->getContentHash());
        $this->assertSame('account-2', $navyk->getUpdatedBy());
        $this->assertSame(self::NOW + 10, $navyk->getUpdatedAt());
        $this->assertSame(self::NOW, $navyk->getCreatedAt());
    }

    #[Test]
    public function izmenenie_bez_izmeneniya_soderzhimogo_ne_podnimaet_versiyu(): void
    {
        $navyk = $this->sozdat();

        $izmenilos = $navyk->izmenit('account-2', self::NOW + 10, content: 'Тело навыка');

        $this->assertFalse($izmenilos);
        $this->assertSame(1, $navyk->getVersion());
        $this->assertSame(self::NOW, $navyk->getUpdatedAt());
    }

    #[Test]
    public function smena_slug_ne_podnimaet_versiyu_no_obnovlyaet_updated_at(): void
    {
        $navyk = $this->sozdat();

        $izmenilos = $navyk->izmenit('account-2', self::NOW + 10, slug: 'another-slug');

        $this->assertFalse($izmenilos);
        $this->assertSame(1, $navyk->getVersion());
        $this->assertSame('another-slug', $navyk->getSlug());
        $this->assertSame(self::NOW + 10, $navyk->getUpdatedAt());
        $this->assertSame('account-2', $navyk->getUpdatedBy());
    }

    #[Test]
    public function arhivirovanie_idempotentno(): void
    {
        $navyk = $this->sozdat();

        $navyk->arhivirovat('account-2', self::NOW + 10);
        $navyk->arhivirovat('account-3', self::NOW + 20);

        $this->assertTrue($navyk->isArchived());
        $this->assertSame(self::NOW + 10, $navyk->getArchivedAt());
        $this->assertSame('account-2', $navyk->getUpdatedBy());
    }

    #[Test]
    public function nevalidnyi_slug_ne_sozdaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.slug.invalid');

        NavykOpenCode::create('skill-1', 'BAD SLUG', 'name', '', '', true, [], 'account-1', self::NOW);
    }
}
