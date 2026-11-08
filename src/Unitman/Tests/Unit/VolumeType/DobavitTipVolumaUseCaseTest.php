<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\VolumeType;

use App\Unitman\Business\Command\VolumeType\DobavitTipVoluma;
use App\Unitman\Business\UseCase\VolumeType\DobavitTipVolumaUseCase;
use DomainException;

/**
 * Создание типа тома в каталоге проекта.
 */
final class DobavitTipVolumaUseCaseTest extends AbstractKatalogUseCase
{
    private DobavitTipVolumaUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useCase = new DobavitTipVolumaUseCase(
            $this->proverkaDostupa,
            $this->typeRepository,
            $this->guidGenerator
        );
    }

    /**
     * @test
     */
    public function tip_sohranyaetsya_v_kataloge_proekta(): void
    {
        $this->useCase->handle(new DobavitTipVoluma(self::PROJECT_ID, 'logs', 'Логи'));

        $type = $this->typeRepository->getByProjectAndCode(self::PROJECT_ID, 'logs');

        $this->assertSame('logs', $type->code);
        $this->assertSame('Логи', $type->name);
        $this->assertSame(self::PROJECT_ID, $type->projectId);
    }

    /**
     * @test
     */
    public function kod_i_nazvanie_ochishchayutsya_ot_probelov(): void
    {
        $this->useCase->handle(new DobavitTipVoluma(self::PROJECT_ID, '  logs  ', '  Логи  '));

        $type = $this->typeRepository->getByProjectAndCode(self::PROJECT_ID, 'logs');

        $this->assertSame('logs', $type->code);
        $this->assertSame('Логи', $type->name);
    }

    /**
     * @test
     */
    public function pustoi_kod_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.code_is_empty');

        $this->useCase->handle(new DobavitTipVoluma(self::PROJECT_ID, '   ', 'Логи'));
    }

    /**
     * @test
     */
    public function pustoe_nazvanie_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.name_is_empty');

        $this->useCase->handle(new DobavitTipVoluma(self::PROJECT_ID, 'logs', '   '));
    }

    /**
     * @test
     */
    public function slishkom_dlinnyi_kod_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.code_is_too_long');

        $this->useCase->handle(new DobavitTipVoluma(self::PROJECT_ID, str_repeat('a', 101), 'Логи'));
    }

    /**
     * @test
     */
    public function slishkom_dlinnoe_nazvanie_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.name_is_too_long');

        $this->useCase->handle(new DobavitTipVoluma(self::PROJECT_ID, 'logs', str_repeat('a', 256)));
    }

    /**
     * @test
     */
    public function povtornyi_kod_v_proekte_otklonyaetsya(): void
    {
        $this->dobavitTip('logs');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.code_is_not_unique');

        $this->useCase->handle(new DobavitTipVoluma(self::PROJECT_ID, 'logs', 'Логи'));
    }

    /**
     * @test
     */
    public function odinakovyi_kod_v_raznyh_proektah_razreshen(): void
    {
        $this->dobavitTip('logs', 'Логи', self::PROJECT_ID);

        $this->securityService->ustanovitPolzovatelya(self::OTHER_ADMIN_ID);
        $this->useCase->handle(new DobavitTipVoluma(self::OTHER_PROJECT_ID, 'logs', 'Логи'));

        $type = $this->typeRepository->getByProjectAndCode(self::OTHER_PROJECT_ID, 'logs');

        $this->assertSame(self::OTHER_PROJECT_ID, $type->projectId);
    }

    /**
     * @test
     */
    public function ne_admin_otklonyaetsya(): void
    {
        $this->dobavitUchastnika(self::USER_ID);
        $this->securityService->ustanovitPolzovatelya(self::USER_ID, false);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('security.access_denied');

        $this->useCase->handle(new DobavitTipVoluma(self::PROJECT_ID, 'logs', 'Логи'));
    }

    /**
     * @test
     */
    public function polzovatel_vne_proekta_otklonyaetsya(): void
    {
        $this->securityService->ustanovitPolzovatelya(self::STRANGER_ID);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('project.user_not_found');

        $this->useCase->handle(new DobavitTipVoluma(self::PROJECT_ID, 'logs', 'Логи'));
    }
}
