<?php

declare(strict_types=1);

namespace App\Skill\Tests\Business\Utils;

use App\Skill\Business\Utils\ProverkaNavyka;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Правила валидации навыка: slug, границы полей, UTF-8 без NUL, область
 * применения и детерминированный хэш содержимого.
 */
final class ProverkaNavykaTest extends TestCase
{
    #[Test]
    public function dopustimye_slug_prohodyat(): void
    {
        // По утверждённому шаблону завершающий дефис допустим.
        foreach (['a', 'a1', 'my-skill', 'skill-2026', 'trailing-', str_repeat('a', 64)] as $slug) {
            ProverkaNavyka::proveritSlug($slug);
        }

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function nedopustimye_slug_otklonyayutsya(): void
    {
        foreach ([
            '',
            'UPPER',
            '-leading',
            'under_score',
            'with space',
            'кириллица',
            str_repeat('a', 65),
        ] as $slug) {
            try {
                ProverkaNavyka::proveritSlug($slug);
                $this->fail('Ожидалось исключение для slug: ' . $slug);
            } catch (\DomainException $e) {
                $this->assertSame('skill.slug.invalid', $e->getMessage());
            }
        }
    }

    #[Test]
    public function nazvanie_do_128_simvolov_razresheno(): void
    {
        ProverkaNavyka::proveritNazvanie(str_repeat('ж', 128));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function nazvanie_dlinnee_128_simvolov_zapreshcheno(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.name.too_long');

        ProverkaNavyka::proveritNazvanie(str_repeat('ж', 129));
    }

    #[Test]
    public function opisanie_do_512_simvolov_razresheno(): void
    {
        ProverkaNavyka::proveritOpisanie(str_repeat('я', 512));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function opisanie_dlinnee_512_simvolov_zapreshcheno(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.description.too_long');

        ProverkaNavyka::proveritOpisanie(str_repeat('я', 513));
    }

    #[Test]
    public function soderzhimoe_do_32_kib_razresheno(): void
    {
        ProverkaNavyka::proveritSoderzhimoe(str_repeat('a', ProverkaNavyka::MAX_CONTENT_BYTES));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function soderzhimoe_bolshe_32_kib_zapreshcheno(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.content.too_long');

        ProverkaNavyka::proveritSoderzhimoe(str_repeat('a', ProverkaNavyka::MAX_CONTENT_BYTES + 1));
    }

    #[Test]
    public function nevalidnyi_utf8_v_soderzhimom_zapreshchen(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.content.invalid_utf8');

        ProverkaNavyka::proveritSoderzhimoe("\xC3\x28");
    }

    #[Test]
    public function nul_v_soderzhimom_zapreshchen(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.content.contains_nul');

        ProverkaNavyka::proveritSoderzhimoe("a\0b");
    }

    #[Test]
    public function proektnyi_navyk_trebuet_hozya_byi_odin_proekt(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.project.required');

        ProverkaNavyka::proveritOblast(false, []);
    }

    #[Test]
    public function globalnyi_navyk_mozhet_byt_bez_proektov(): void
    {
        ProverkaNavyka::proveritOblast(true, []);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function proekty_normalizuyutsya_i_dedupiruyutsya(): void
    {
        $this->assertSame(
            ['p-1', 'p-2'],
            ProverkaNavyka::normalizovatProekty(['p-1', ' p-2 ', 'p-1']),
        );
    }

    #[Test]
    public function pustoi_identifikator_proekta_zapreshchen(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.project.invalid');

        ProverkaNavyka::normalizovatProekty(['   ']);
    }

    #[Test]
    public function hash_determinirovan_i_zavisit_ot_vseh_treh_polei(): void
    {
        $hash = ProverkaNavyka::hashKontenta('name', 'description', 'content');

        $this->assertSame(hash('sha256', 'name|description|content'), $hash);
        $this->assertSame($hash, ProverkaNavyka::hashKontenta('name', 'description', 'content'));
        $this->assertNotSame($hash, ProverkaNavyka::hashKontenta('name', 'description', 'content!'));
        $this->assertNotSame($hash, ProverkaNavyka::hashKontenta('name2', 'description', 'content'));
        $this->assertNotSame($hash, ProverkaNavyka::hashKontenta('name', 'description2', 'content'));
    }
}
