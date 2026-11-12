<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\VolumeType;

use App\Unitman\Business\Command\VolumeType\PoluchitTipyVolumov;
use App\Unitman\Business\UseCase\VolumeType\PoluchitTipyVolumovQuery;
use DomainException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Чтение каталога типов томов проекта.
 */
final class PoluchitTipyVolumovQueryTest extends AbstractKatalogUseCase
{
    private PoluchitTipyVolumovQuery $query;

    protected function setUp(): void
    {
        parent::setUp();

        $this->query = new PoluchitTipyVolumovQuery(
            $this->proverkaDostupa,
            $this->typeRepository,
            $this->fileRepository,
            $this->mediatekaService
        );
    }

    #[Test]
    public function tipy_vozvrashchayutsya_otsortirovannymi_po_kodu(): void
    {
        $this->dobavitTip('logs', 'Логи');
        $this->dobavitTip('cache', 'Кэш');

        $result = $this->query->handle(new PoluchitTipyVolumov(self::PROJECT_ID));

        $this->assertCount(2, $result);
        $this->assertSame('cache', $result[0]->code);
        $this->assertSame('Кэш', $result[0]->name);
        $this->assertSame('logs', $result[1]->code);
    }

    #[Test]
    public function tipy_chuzhogo_proekta_ne_popadayut_v_vydachu(): void
    {
        $this->dobavitTip('logs', 'Логи', self::PROJECT_ID);
        $this->dobavitTip('other', 'Чужой', self::OTHER_PROJECT_ID);

        $result = $this->query->handle(new PoluchitTipyVolumov(self::PROJECT_ID));

        $this->assertCount(1, $result);
        $this->assertSame('logs', $result[0]->code);
    }

    #[Test]
    public function fayly_tipa_vozvrashchayutsya_s_metadannymi(): void
    {
        $this->tipSFaylom('logs', 'file-1');
        $this->mediatekaService->faylyProekta['file-1'] = [
            'name' => 'logs.zip',
            'size' => 2048,
            'createdAt' => 1700000000,
        ];

        $result = $this->query->handle(new PoluchitTipyVolumov(self::PROJECT_ID));

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]->files);

        $file = $result[0]->files[0];
        $this->assertSame('file-1', $file->fileId);
        $this->assertSame('logs.zip', $file->name);
        $this->assertSame(2048, $file->size);
        $this->assertSame(1700000000, $file->createdAt);
    }

    #[Test]
    public function fayl_bez_metadannyh_propuskaetsya(): void
    {
        $type = $this->dobavitTip('logs');
        $this->storage->links[] = new \App\Unitman\Business\Model\VolumeType\VolumeTypeFile(
            $type->id,
            self::PROJECT_ID,
            'file-1',
            1000
        );

        $result = $this->query->handle(new PoluchitTipyVolumov(self::PROJECT_ID));

        $this->assertCount(1, $result);
        $this->assertSame([], $result[0]->files);
    }

    #[Test]
    public function to_array_otdayot_kontrakt_otveta(): void
    {
        $this->tipSFaylom('logs', 'file-1');
        $this->mediatekaService->faylyProekta['file-1'] = [
            'name' => 'logs.zip',
            'size' => 100,
            'createdAt' => 1000,
        ];

        $result = $this->query->handle(new PoluchitTipyVolumov(self::PROJECT_ID));

        $this->assertSame(
            [
                [
                    'code' => 'logs',
                    'name' => 'Тип logs',
                    'files' => [
                        [
                            'fileId' => 'file-1',
                            'name' => 'logs.zip',
                            'size' => 100,
                            'createdAt' => 1000,
                        ],
                    ],
                ],
            ],
            array_map(static fn ($item): array => $item->toArray(), $result)
        );
    }

    #[Test]
    public function uchastnik_proekta_mozhet_chitat_katalog(): void
    {
        $this->dobavitTip('logs');
        $this->dobavitUchastnika(self::USER_ID);
        $this->securityService->ustanovitPolzovatelya(self::USER_ID, false);

        $result = $this->query->handle(new PoluchitTipyVolumov(self::PROJECT_ID));

        $this->assertCount(1, $result);
    }

    #[Test]
    public function polzovatel_vne_proekta_otklonyaetsya(): void
    {
        $this->securityService->ustanovitPolzovatelya(self::STRANGER_ID);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('project.user_not_found');

        $this->query->handle(new PoluchitTipyVolumov(self::PROJECT_ID));
    }
}
