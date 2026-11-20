<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\SozdatWorkspace;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\UseCase\SozdatWorkspaceUseCase;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SozdatWorkspaceUseCaseTest extends TestCase
{
    #[Test]
    public function sozdaet_sessiyu_i_zapuskaet_workspace(): void
    {
        $saved = null;

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('findByAccountId')->willReturn([]);
        $repository->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (SessiyaOpenCode $session) use (&$saved): void {
                $saved = $session;
            });
        $repository->expects($this->once())->method('update')->with($this->isInstanceOf(SessiyaOpenCode::class));

        $credentials = $this->credentials();

        $projectami = $this->createMock(UmeetUpravlyatProektamiWorkspace::class);
        $projectami->expects($this->once())
            ->method('poluchitProekti')
            ->with(['project-1', 'project-2'], 'account-1')
            ->willReturn([
                FabrikaWorkspace::proekt('project-1', 'api', 'services/api', true, 0),
                FabrikaWorkspace::proekt('project-2', 'web', 'apps/web', false, 1),
            ]);

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->expects($this->once())->method('zapustit');

        $uuid = $this->createMock(UuidGenerator::class);
        $uuid->method('makeGuid')->willReturn('workspace-1');

        $useCase = new SozdatWorkspaceUseCase($repository, $credentials, $projectami, $manager, $uuid);

        $id = $useCase->handle($this->komanda(['project-1', 'project-2']));

        $this->assertSame('workspace-1', $id);
        $this->assertInstanceOf(SessiyaOpenCode::class, $saved);
        $this->assertSame('payments-refactor', $saved->getTitle());
        $this->assertTrue($saved->estWorkspace());
        $this->assertSame(StatusWorkspace::CREATING, $saved->getWorkspace()?->getStatus());
        $this->assertTrue($saved->getWorkspace()?->isReadOnly(), 'canPush=false хотя бы у одного проекта делает workspace read-only');
        $this->assertSame(
            ['project-1', 'project-2'],
            array_map(
                static fn (ProektWorkspace $project): string => $project->projectId,
                $saved->getWorkspace()?->getProjects() ?? [],
            ),
        );
    }

    #[Test]
    public function chuzhie_rekvizity_ne_sozdayut_sessiyu(): void
    {
        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->expects($this->never())->method('save');

        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('account-1');
        $credentials->method('trebovatVladelca')->willThrowException(
            new \DomainException('opencode.session.not_found'),
        );

        $useCase = new SozdatWorkspaceUseCase(
            $repository,
            $credentials,
            $this->createMock(UmeetUpravlyatProektamiWorkspace::class),
            $this->createMock(UmeetUpravlyatWorkspace::class),
            $this->createMock(UuidGenerator::class),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.not_found');

        $useCase->handle($this->komanda(['project-1']));
    }

    #[Test]
    public function dublikat_proekta_otklonyaetsya_do_obrashcheniya_v_unitman(): void
    {
        $projectami = $this->createMock(UmeetUpravlyatProektamiWorkspace::class);
        $projectami->expects($this->never())->method('poluchitProekti');

        $useCase = new SozdatWorkspaceUseCase(
            $this->createMock(SessiyaOpenCodeRepository::class),
            $this->credentials(),
            $projectami,
            $this->createMock(UmeetUpravlyatWorkspace::class),
            $this->createMock(UuidGenerator::class),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.project_conflict');

        $useCase->handle($this->komanda(['project-1', 'project-1']));
    }

    #[Test]
    public function povtornoe_imya_otklonyaetsya(): void
    {
        $existing = FabrikaWorkspace::sozdanie('other', 'payments-refactor');

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('findByAccountId')->willReturn([$existing]);
        $repository->expects($this->never())->method('save');

        $useCase = new SozdatWorkspaceUseCase(
            $repository,
            $this->credentials(),
            $this->createMock(UmeetUpravlyatProektamiWorkspace::class),
            $this->createMock(UmeetUpravlyatWorkspace::class),
            $this->createMock(UuidGenerator::class),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.name_already_exists');

        $useCase->handle($this->komanda(['project-1']));
    }

    #[Test]
    public function otkaz_rannera_perevodit_workspace_v_error_no_id_vozvrashchaetsya(): void
    {
        $updated = null;

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('findByAccountId')->willReturn([]);
        $repository->expects($this->once())->method('save');
        $repository->expects($this->once())
            ->method('update')
            ->willReturnCallback(static function (SessiyaOpenCode $session) use (&$updated): void {
                $updated = $session;
            });

        $projectami = $this->createMock(UmeetUpravlyatProektamiWorkspace::class);
        $projectami->method('poluchitProekti')->willReturn([FabrikaWorkspace::proekt()]);

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->method('zapustit')->willThrowException(new \RuntimeException('temporal unavailable'));

        $uuid = $this->createMock(UuidGenerator::class);
        $uuid->method('makeGuid')->willReturn('workspace-1');

        $useCase = new SozdatWorkspaceUseCase($repository, $this->credentials(), $projectami, $manager, $uuid);

        $this->assertSame('workspace-1', $useCase->handle($this->komanda(['project-1'])));
        $this->assertInstanceOf(SessiyaOpenCode::class, $updated);
        $this->assertSame(StatusWorkspace::ERROR, $updated->getWorkspace()?->getStatus());
        $this->assertSame('opencode.workspace.operation_failed', $updated->getWorkspace()?->getErrorKey());
    }

    private function credentials(): UmeetProveryatRekvizityOpenCode
    {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('account-1');
        $credentials->expects($this->once())->method('trebovatVladelca')->with('account-1', 'credential-1');

        return $credentials;
    }

    /**
     * @param list<string> $projectIds
     */
    private function komanda(array $projectIds): SozdatWorkspace
    {
        return new SozdatWorkspace('payments-refactor', 'feature/payments', $projectIds, 'Первый промпт', 'credential-1');
    }
}
