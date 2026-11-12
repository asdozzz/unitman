<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\VolumeType;

use App\Unitman\Business\Command\VolumeType\OtkrepitFaylOtTipaVoluma;
use App\Unitman\Business\UseCase\VolumeType\OtkrepitFaylOtTipaVolumaUseCase;
use DomainException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Отвязка архива от типа тома.
 */
final class OtkrepitFaylOtTipaVolumaUseCaseTest extends AbstractKatalogUseCase
{
    private OtkrepitFaylOtTipaVolumaUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useCase = new OtkrepitFaylOtTipaVolumaUseCase(
            $this->proverkaDostupa,
            $this->typeRepository,
            $this->fileRepository,
            $this->mediatekaService
        );
    }

    #[Test]
    public function svyaz_udalyayetsya_fayl_ostayotsya_v_mediateke(): void
    {
        $type = $this->tipSFaylom('logs', 'file-1');

        $this->useCase->handle(new OtkrepitFaylOtTipaVoluma(self::PROJECT_ID, 'logs', 'file-1'));

        $this->assertFalse($this->fileRepository->esliSvyazEst($type->id, 'file-1'));
        $this->assertSame([], $this->mediatekaService->udalennieFaylyProekta);
        $this->assertArrayHasKey('file-1', $this->mediatekaService->vladelcy);
    }

    #[Test]
    public function udalit_fayl_udalyayet_arhiv_iz_mediateki(): void
    {
        $type = $this->tipSFaylom('logs', 'file-1');

        $this->useCase->handle(new OtkrepitFaylOtTipaVoluma(self::PROJECT_ID, 'logs', 'file-1', true));

        $this->assertFalse($this->fileRepository->esliSvyazEst($type->id, 'file-1'));
        $this->assertSame(['file-1'], $this->mediatekaService->udalennieFaylyProekta);
        $this->assertArrayNotHasKey('file-1', $this->mediatekaService->vladelcy);
    }

    #[Test]
    public function pustoi_file_id_otklonyaetsya(): void
    {
        $this->dobavitTip('logs');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.file_id_is_empty');

        $this->useCase->handle(new OtkrepitFaylOtTipaVoluma(self::PROJECT_ID, 'logs', '  '));
    }

    #[Test]
    public function otsutstvuyushchaya_svyaz_otklonyaetsya(): void
    {
        $this->dobavitTip('logs');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.file_not_linked');

        $this->useCase->handle(new OtkrepitFaylOtTipaVoluma(self::PROJECT_ID, 'logs', 'file-1'));
    }

    #[Test]
    public function neizvestnyi_tip_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.not_found');

        $this->useCase->handle(new OtkrepitFaylOtTipaVoluma(self::PROJECT_ID, 'logs', 'file-1'));
    }

    #[Test]
    public function ne_admin_otklonyaetsya(): void
    {
        $this->tipSFaylom('logs', 'file-1');
        $this->dobavitUchastnika(self::USER_ID);
        $this->securityService->ustanovitPolzovatelya(self::USER_ID, false);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('security.access_denied');

        $this->useCase->handle(new OtkrepitFaylOtTipaVoluma(self::PROJECT_ID, 'logs', 'file-1'));
    }
}
