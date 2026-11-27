<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\PrinyatFajlHoda;
use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\ReadModel\FajlHodaReadModel;
use App\OpenCode\Business\UseCase\PrinyatFajlHodaUseCase;
use App\OpenCode\Business\Utils\ImyaFajlaHoda;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Порядок проверок приёма артефакта и лимиты.
 *
 * Ключевой инвариант: до нормализации имени и до чтения статистики ход обязан
 * быть RUNNING, а сессия — workspace, тем же самым workspace и не в архиве.
 */
final class PrinyatFajlHodaUseCaseTest extends TestCase
{
    private const WORKFLOW_ID = 'workflow-1';
    private const TURN_ID = 'turn-1';
    private const WORKSPACE_ID = 'workspace-1';
    private const SESSION_ID = 'session-1';

    #[Test]
    public function uspeshno_registriruet_fajl_s_normalizovannym_imenem(): void
    {
        $files = $this->files();
        $files->expects($this->once())
            ->method('zaregistrirovatFajlHoda')
            ->with(
                self::WORKSPACE_ID,
                'account-1',
                self::TURN_ID,
                'plan.md',
                'text/markdown',
                '# План',
                $this->isType('int'),
            )
            ->willReturn($this->readModel('file-1', 'plan.md'));

        $file = $this->useCase($files)->handle($this->komanda('../plan.MD', '# План'));

        self::assertSame('file-1', $file->fileId);
        self::assertSame('plan.md', $file->name);
    }

    #[Test]
    public function neizvestnyj_hod_otklonyaetsya_do_chego_libo_eshche(): void
    {
        $turns = $this->createMock(HodOpenCodeRepository::class);
        $turns->method('findByWorkflowId')->willReturn(null);

        $sessions = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessions->expects($this->never())->method('findById');

        $files = $this->createMock(UmeetUpravlyatFaylamiWorkspace::class);
        $files->expects($this->never())->method('poluchitStatistikuFajlovHoda');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.artifact.turn_not_found');

        (new PrinyatFajlHodaUseCase(
            $turns,
            $sessions,
            $files,
            $this->imya(),
            2_097_152,
            5,
            8_388_608,
        ))->handle($this->komanda('plan.md', '# План'));
    }

    #[Test]
    public function nezavershennyj_hod_otklonyaetsya_do_raboty_s_sessiej(): void
    {
        $turns = $this->createMock(HodOpenCodeRepository::class);
        $turns->method('findByWorkflowId')->willReturn($this->turn('ANSWERED'));

        $sessions = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessions->expects($this->never())->method('findById');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.artifact.turn_not_running');

        $this->useCase($this->files(), $turns, $sessions)
            ->handle($this->komanda('plan.md', '# План'));
    }

    #[Test]
    public function unit_sessiya_otklonyaetsya(): void
    {
        $sessions = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessions->method('findById')->willReturn(
            SessiyaOpenCode::create(self::SESSION_ID, 'account-1', 'cred-1', null, [], 1000),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.artifact.workspace_required');

        $this->useCase($this->files(), null, $sessions)
            ->handle($this->komanda('plan.md', '# План'));
    }

    #[Test]
    public function chuzhoj_workspace_otklonyaetsya(): void
    {
        $sessions = $this->mockSessions(FabrikaWorkspace::sozdanie('other-workspace'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.artifact.workspace_mismatch');

        $this->useCase($this->files(), null, $sessions)
            ->handle($this->komanda('plan.md', '# План'));
    }

    #[Test]
    public function arhivnyj_workspace_otklonyaetsya(): void
    {
        $session = FabrikaWorkspace::sozdanie();
        $session->arhivirovat(SessiyaOpenCode::ARCHIVE_REASON_MANUAL, FabrikaWorkspace::NOW + 1);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.artifact.workspace_archived');

        $this->useCase($this->files(), null, $this->mockSessions($session))
            ->handle($this->komanda('plan.md', '# План'));
    }

    #[Test]
    public function nedopustimoe_imya_otklonyaetsya_do_statistiki(): void
    {
        $files = $this->createMock(UmeetUpravlyatFaylamiWorkspace::class);
        $files->expects($this->never())->method('poluchitStatistikuFajlovHoda');
        $files->expects($this->never())->method('zaregistrirovatFajlHoda');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.artifact.name_is_invalid');

        $this->useCase($files)->handle($this->komanda('.env.md', '# План'));
    }

    #[Test]
    public function zapreshchennyj_tip_otklonyaetsya(): void
    {
        $files = $this->createMock(UmeetUpravlyatFaylamiWorkspace::class);
        $files->expects($this->never())->method('poluchitStatistikuFajlovHoda');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.artifact.file_type_forbidden');

        $this->useCase($files)->handle($this->komanda('plan.html', '<html>'));
    }

    #[Test]
    public function pustoe_soderzhimoe_otklonyaetsya(): void
    {
        $files = $this->createMock(UmeetUpravlyatFaylamiWorkspace::class);
        $files->expects($this->never())->method('zaregistrirovatFajlHoda');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.artifact.content_invalid');

        $this->useCase($files)->handle($this->komanda('plan.md', ''));
    }

    #[Test]
    public function nul_bajt_otklonyaetsya(): void
    {
        $files = $this->createMock(UmeetUpravlyatFaylamiWorkspace::class);
        $files->expects($this->never())->method('zaregistrirovatFajlHoda');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.artifact.content_invalid');

        $this->useCase($files)->handle($this->komanda('plan.md', "a\0b"));
    }

    #[Test]
    public function nevalidnyj_utf8_otklonyaetsya(): void
    {
        $files = $this->createMock(UmeetUpravlyatFaylamiWorkspace::class);
        $files->expects($this->never())->method('zaregistrirovatFajlHoda');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.artifact.content_invalid');

        $this->useCase($files)->handle($this->komanda('plan.md', "\xC3\x28"));
    }

    #[Test]
    public function prevyshenie_kolichestva_fajlov_otklonyaetsya(): void
    {
        $files = $this->files(['count' => 5, 'totalSize' => 10]);
        $files->expects($this->never())->method('zaregistrirovatFajlHoda');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.artifact.too_many_files');

        $this->useCase($files)->handle($this->komanda('plan.md', '# План'));
    }

    #[Test]
    public function prevyshenie_razmera_fajla_otklonyaetsya(): void
    {
        $files = $this->files(['count' => 0, 'totalSize' => 0]);
        $files->expects($this->never())->method('zaregistrirovatFajlHoda');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.artifact.file_is_too_large');

        (new PrinyatFajlHodaUseCase(
            $this->turns(),
            $this->mockSessions(FabrikaWorkspace::sozdanie()),
            $files,
            $this->imya(),
            4,
            5,
            8_388_608,
        ))->handle($this->komanda('plan.md', '# План'));
    }

    #[Test]
    public function prevyshenie_summarnogo_razmera_otklonyaetsya(): void
    {
        $files = $this->files(['count' => 1, 'totalSize' => 10]);
        $files->expects($this->never())->method('zaregistrirovatFajlHoda');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.artifact.total_is_too_large');

        (new PrinyatFajlHodaUseCase(
            $this->turns(),
            $this->mockSessions(FabrikaWorkspace::sozdanie()),
            $files,
            $this->imya(),
            2_097_152,
            5,
            6,
        ))->handle($this->komanda('plan.md', "# План\n"));
    }

    private function komanda(string $name, string $content): PrinyatFajlHoda
    {
        return new PrinyatFajlHoda(
            self::WORKFLOW_ID,
            self::WORKSPACE_ID,
            $name,
            'text/markdown',
            $content,
        );
    }

    private function useCase(
        UmeetUpravlyatFaylamiWorkspace $files,
        ?HodOpenCodeRepository $turns = null,
        ?SessiyaOpenCodeRepository $sessions = null,
    ): PrinyatFajlHodaUseCase {
        return new PrinyatFajlHodaUseCase(
            $turns ?? $this->turns(),
            $sessions ?? $this->mockSessions(FabrikaWorkspace::sozdanie()),
            $files,
            $this->imya(),
            2_097_152,
            5,
            8_388_608,
        );
    }

    private function turns(): HodOpenCodeRepository
    {
        $turns = $this->createMock(HodOpenCodeRepository::class);
        $turns->method('findByWorkflowId')->willReturn($this->turn());

        return $turns;
    }

    private function turn(string $status = 'RUNNING'): HodOpenCode
    {
        return HodOpenCode::restore(
            self::TURN_ID,
            self::WORKSPACE_ID,
            1,
            self::WORKFLOW_ID,
            $status,
            'Привет',
            [],
            null,
            null,
            1000,
            null,
        );
    }

    private function mockSessions(SessiyaOpenCode $session): SessiyaOpenCodeRepository
    {
        $sessions = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessions->method('findById')->willReturn($session);

        return $sessions;
    }

    /**
     * @param array{count: int, totalSize: int} $stat
     */
    private function files(array $stat = ['count' => 0, 'totalSize' => 0]): UmeetUpravlyatFaylamiWorkspace
    {
        $files = $this->createMock(UmeetUpravlyatFaylamiWorkspace::class);
        $files->method('poluchitStatistikuFajlovHoda')->willReturn($stat);

        return $files;
    }

    private function readModel(string $fileId, string $name): FajlHodaReadModel
    {
        return new FajlHodaReadModel(
            $fileId,
            self::TURN_ID,
            self::WORKSPACE_ID,
            $name,
            6,
            'text/markdown',
            1000,
        );
    }

    private function imya(): ImyaFajlaHoda
    {
        return new ImyaFajlaHoda('md,txt,json,yaml,yml,csv,log');
    }
}
