<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\PrikrepitFaylyKWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\Workspace;
use App\OpenCode\Business\Model\WorkspacePendingUpdate;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\ReadModel\WorkspaceFileReadModel;
use App\OpenCode\Business\UseCase\PrikrepitFaylyKWorkspaceUseCase;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use App\OpenCode\Tests\Support\ImitaciyaUpravleniyaFaylamiWorkspace;
use App\OpenCode\Tests\Support\ImitaciyaUpravleniyaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Прикрепление файлов к работающему workspace: черновики привязываются,
 * обновление запускается, при ошибке привязка откатывается.
 */
final class PrikrepitFaylyKWorkspaceUseCaseTest extends TestCase
{
    #[Test]
    public function prikreplyaet_fayly_i_zapuskaet_obnovlenie(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->with('workspace-1')->willReturn($session);
        $repository->method('findWorkspaceUpdate')->with('workspace-1')->willReturn(null);

        $savedPending = null;
        $repository->expects($this->once())
            ->method('saveWorkspaceUpdate')
            ->willReturnCallback(static function (string $id, string $workflowId, WorkspacePendingUpdate $update) use (&$savedPending): void {
                $savedPending = $update;
            });
        $repository->expects($this->once())->method('update')->with($session);

        $fayly = new ImitaciyaUpravleniyaFaylamiWorkspace();
        $fayly->zadatChernovik($this->file('file-1', 'notes.txt'));

        $manager = new ImitaciyaUpravleniyaWorkspace();
        $manager->zadatWorkflowObnovleniya('wf-attach-files');

        $useCase = new PrikrepitFaylyKWorkspaceUseCase(
            $repository,
            $this->credentials(),
            $fayly,
            $manager,
            20,
        );

        $useCase->handle(new PrikrepitFaylyKWorkspace('workspace-1', ['file-1', 'file-1', '']));

        $this->assertCount(1, $manager->obnovleniya());
        $pending = $manager->obnovleniya()[0]['update'];
        $this->assertSame(WorkspacePendingUpdate::KIND_FILES, $pending->kind);
        $this->assertSame([['fileId' => 'file-1', 'name' => 'notes.txt']], $pending->files);
        $this->assertSame($pending, $savedPending);
        $this->assertSame(Workspace::OPERATION_UPDATE, $session->getWorkspace()?->getOperation());
    }

    #[Test]
    public function nedostupnyi_fayl_otklonyaetsya(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn($session);
        $repository->method('findWorkspaceUpdate')->willReturn(null);
        $repository->expects($this->never())->method('saveWorkspaceUpdate');

        $fayly = new ImitaciyaUpravleniyaFaylamiWorkspace();
        $fayly->zadatDostupnost(false);

        $useCase = new PrikrepitFaylyKWorkspaceUseCase(
            $repository,
            $this->credentials(),
            $fayly,
            new ImitaciyaUpravleniyaWorkspace(),
            20,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.file_not_found');

        $useCase->handle(new PrikrepitFaylyKWorkspace('workspace-1', ['file-1']));
    }

    #[Test]
    public function konflikt_imeni_otklonyaetsya(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn($session);
        $repository->method('findWorkspaceUpdate')->willReturn(null);
        $repository->expects($this->never())->method('saveWorkspaceUpdate');

        $fayly = new ImitaciyaUpravleniyaFaylamiWorkspace();
        $fayly->zadatFayly('workspace-1', [$this->file('existing', 'notes.txt')]);
        $fayly->zadatChernovik($this->file('file-1', 'notes.txt'));

        $useCase = new PrikrepitFaylyKWorkspaceUseCase(
            $repository,
            $this->credentials(),
            $fayly,
            new ImitaciyaUpravleniyaWorkspace(),
            20,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.file_name_conflict');

        $useCase->handle(new PrikrepitFaylyKWorkspace('workspace-1', ['file-1']));
    }

    #[Test]
    public function prevyshenie_limita_otklonyaetsya(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn($session);
        $repository->method('findWorkspaceUpdate')->willReturn(null);
        $repository->expects($this->never())->method('saveWorkspaceUpdate');

        $fayly = new ImitaciyaUpravleniyaFaylamiWorkspace();
        $fayly->zadatChernovik($this->file('file-1', 'notes.txt'));

        $useCase = new PrikrepitFaylyKWorkspaceUseCase(
            $repository,
            $this->credentials(),
            $fayly,
            new ImitaciyaUpravleniyaWorkspace(),
            0,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.file_limit_exceeded');

        $useCase->handle(new PrikrepitFaylyKWorkspace('workspace-1', ['file-1']));
    }

    #[Test]
    public function oshibka_zapuska_otkatyvaet_prikreplenie(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn($session);
        $repository->method('findWorkspaceUpdate')->willReturn(null);
        $repository->expects($this->never())->method('saveWorkspaceUpdate');

        $fayly = new ImitaciyaUpravleniyaFaylamiWorkspace();
        $fayly->zadatChernovik($this->file('file-1', 'notes.txt'));

        $manager = new ImitaciyaUpravleniyaWorkspace();
        $manager->zadatOshibkuObnovleniya(new \DomainException('runner unavailable'));

        $useCase = new PrikrepitFaylyKWorkspaceUseCase(
            $repository,
            $this->credentials(),
            $fayly,
            $manager,
            20,
        );

        try {
            $useCase->handle(new PrikrepitFaylyKWorkspace('workspace-1', ['file-1']));
            $this->fail('ожидалось исключение при ошибке запуска обновления');
        } catch (\DomainException $error) {
            $this->assertSame('runner unavailable', $error->getMessage());
        }

        $this->assertSame(['file-1'], $fayly->udalennye());
    }

    private function file(string $id, string $name): WorkspaceFileReadModel
    {
        return new WorkspaceFileReadModel($id, $name, 10, false, time());
    }

    private function credentials(): UmeetProveryatRekvizityOpenCode
    {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('account-1');

        return $credentials;
    }
}
