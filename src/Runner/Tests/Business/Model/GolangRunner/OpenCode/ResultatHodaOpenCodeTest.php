<?php

declare(strict_types=1);

namespace App\Runner\Tests\Business\Model\GolangRunner\OpenCode;

use App\Runner\Business\Model\GolangRunner\OpenCode\ResultatHodaOpenCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * DTO результата хода раннера: `Attachments` добавлены последним
 * параметром со значением по умолчанию, чтобы старые payload'ы без этого
 * поля продолжали гидрироваться.
 */
final class ResultatHodaOpenCodeTest extends TestCase
{
    #[Test]
    public function staryj_payload_bez_attachments_daet_pustoj_spisok(): void
    {
        $rezultat = new ResultatHodaOpenCode(
            true,
            ResultatHodaOpenCode::KIND_ANSWER,
            [],
            'Готово',
        );

        self::assertSame([], $rezultat->Attachments);
    }

    #[Test]
    public function attachments_idut_poslednim_i_ne_sdvigayut_ostalnye_polya(): void
    {
        $attachments = [
            [
                'FileId' => 'file-1',
                'Name' => 'plan.md',
                'Size' => 42,
                'ContentType' => 'text/markdown',
            ],
        ];

        $rezultat = new ResultatHodaOpenCode(
            true,
            ResultatHodaOpenCode::KIND_ANSWER,
            [['Id' => 'q1']],
            'Ответ',
            null,
            'external-1',
            null,
            $attachments,
        );

        self::assertTrue($rezultat->Success);
        self::assertSame(ResultatHodaOpenCode::KIND_ANSWER, $rezultat->Kind);
        self::assertSame([['Id' => 'q1']], $rezultat->Questions);
        self::assertSame('Ответ', $rezultat->Answer);
        self::assertNull($rezultat->ErrorKey);
        self::assertSame('external-1', $rezultat->ExternalSessionId);
        self::assertNull($rezultat->ErrorMessage);
        self::assertSame($attachments, $rezultat->Attachments);
    }
}
