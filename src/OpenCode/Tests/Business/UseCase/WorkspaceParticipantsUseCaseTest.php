<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\DobavitUchastnika;
use App\OpenCode\Business\Command\IzmenitUchastnika;
use App\OpenCode\Business\Command\PoluchitUchastnikov;
use App\OpenCode\Business\Command\UdalitUchastnika;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\UseCase\DobavitUchastnikaUseCase;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\UseCase\DobavitUchastnikaVWorkspaceSessiiUseCase;
use App\OpenCode\Business\Workspace\UseCase\IzmenitUchastnikaVWorkspaceSessiiUseCase;
use App\OpenCode\Business\Workspace\UseCase\PoluchitUchastnikovWorkspaceSessiiQuery;
use App\OpenCode\Business\Workspace\UseCase\UdalitUchastnikaIzWorkspaceSessiiUseCase;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use App\OpenCode\Tests\Support\ImitaciyaUpravleniyaProektamiWorkspace;
use App\OpenCode\Tests\Support\InMemoryWorkspaceParticipantsRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Участники workspace на новом агрегате под флагами READ/WRITE.
 *
 * Контракт и коды ошибок совпадают с легаси-тестом `UchastnikiWorkspaceUseCaseTest`.
 */
final class WorkspaceParticipantsUseCaseTest extends TestCase
{
    #[Test]
    public function vladelec_dobavlyaet_writer_s_dostupom_k_proektam(): void
    {
        $uchastniki = new InMemoryWorkspaceParticipantsRepository();

        $this->dobavitUseCase($uchastniki, 'account-1')->handle(new DobavitUchastnika(
            'workspace-1',
            'acc-2',
            'writer',
            'cred-2',
            'gpt-4o',
        ));

        $uchastnik = $uchastniki->find('workspace-1', 'acc-2');
        $this->assertNotNull($uchastnik);
        $this->assertSame(RolUchastnikaSessii::WRITER, $uchastnik->getRol());
        $this->assertSame('cred-2', $uchastnik->getDefaultCredentialId());
        $this->assertSame('account-1', $uchastnik->getInvitedBy());
    }

    #[Test]
    public function writer_ne_mozhet_priqlashat_uchastnikov(): void
    {
        $uchastniki = new InMemoryWorkspaceParticipantsRepository();
        $uchastniki->save($this->uchastnik('acc-2', RolUchastnikaSessii::WRITER));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.forbidden');

        $this->dobavitUseCase($uchastniki, 'acc-2')->handle(new DobavitUchastnika(
            'workspace-1',
            'acc-3',
            'viewer',
        ));
    }

    #[Test]
    public function kandidat_bez_dostupa_k_proektu_ne_priqlashaetsya(): void
    {
        $uchastniki = new InMemoryWorkspaceParticipantsRepository();
        $projectami = $this->projectami();
        $projectami->zapretitDostup('acc-2');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.participant.project_access_denied');

        (new DobavitUchastnikaVWorkspaceSessiiUseCase(
            $this->workspaces(),
            $uchastniki,
            $this->credentials('account-1'),
            $projectami,
            new ProverkaDostupaWorkspaceSessii($uchastniki),
        ))->handle(new DobavitUchastnika('workspace-1', 'acc-2', 'viewer'));
    }

    #[Test]
    public function spisok_pokazyvaet_nevyavnogo_vladelca(): void
    {
        $uchastniki = new InMemoryWorkspaceParticipantsRepository();

        $items = (new PoluchitUchastnikovWorkspaceSessiiQuery(
            $this->workspaces(),
            $uchastniki,
            $this->credentials('account-1'),
            new ProverkaDostupaWorkspaceSessii($uchastniki),
        ))->handle(new PoluchitUchastnikov('workspace-1'));

        $this->assertCount(1, $items);
        $this->assertSame('account-1', $items[0]->accountId);
        $this->assertTrue($items[0]->vladelec);
        $this->assertSame('credential-1', $items[0]->defaultCredentialId);
    }

    #[Test]
    public function vladelca_nelzya_ponizit_ili_udalit(): void
    {
        $uchastniki = new InMemoryWorkspaceParticipantsRepository();
        $uchastniki->save($this->uchastnik('account-1', RolUchastnikaSessii::OWNER));

        try {
            (new IzmenitUchastnikaVWorkspaceSessiiUseCase(
                $this->workspaces(),
                $uchastniki,
                $this->credentials('account-1'),
                new ProverkaDostupaWorkspaceSessii($uchastniki),
            ))->handle(new IzmenitUchastnika('workspace-1', 'account-1', 'viewer'));
            $this->fail('Ожидалась ошибка неизменяемой роли владельца');
        } catch (\DomainException $exception) {
            $this->assertSame('opencode.participant.owner_role_immutable', $exception->getMessage());
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.participant.owner_cannot_be_removed');

        (new UdalitUchastnikaIzWorkspaceSessiiUseCase(
            $this->workspaces(),
            $uchastniki,
            $this->credentials('account-1'),
            new ProverkaDostupaWorkspaceSessii($uchastniki),
        ))->handle(new UdalitUchastnika('workspace-1', 'account-1'));
    }

    #[Test]
    public function vladelec_udalyaet_writer(): void
    {
        $uchastniki = new InMemoryWorkspaceParticipantsRepository();
        $uchastniki->save($this->uchastnik('account-1', RolUchastnikaSessii::OWNER));
        $uchastniki->save($this->uchastnik('acc-2', RolUchastnikaSessii::WRITER));

        (new UdalitUchastnikaIzWorkspaceSessiiUseCase(
            $this->workspaces(),
            $uchastniki,
            $this->credentials('account-1'),
            new ProverkaDostupaWorkspaceSessii($uchastniki),
        ))->handle(new UdalitUchastnika('workspace-1', 'acc-2'));

        $this->assertNull($uchastniki->find('workspace-1', 'acc-2'));
        $this->assertSame('acc-2', $uchastniki->udalennyi?->getAccountId());
    }

    #[Test]
    public function pri_vklyuchennom_write_legasi_router_delegiruet_novomu_agregatu(): void
    {
        $uchastniki = new InMemoryWorkspaceParticipantsRepository();

        $legacyRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $legacyRepository->expects($this->never())->method('getById');

        $useCase = new DobavitUchastnikaUseCase(
            $legacyRepository,
            $this->createMock(\App\OpenCode\Business\Port\UchastnikiSessiiRepository::class),
            $this->credentials('account-1'),
            $this->projectami(),
            new \App\OpenCode\Business\Utils\ProverkaDostupaKSessii(
                new \App\OpenCode\Tests\Support\InMemoryUchastnikiSessiiRepository(),
            ),
            new WorkspaceFeatureFlags(read: true, write: true),
            $this->dobavitUseCase($uchastniki, 'account-1'),
        );

        $useCase->handle(new DobavitUchastnika('workspace-1', 'acc-2', 'viewer'));

        $this->assertNotNull($uchastniki->find('workspace-1', 'acc-2'));
    }

    #[Test]
    public function pri_vyklyuchennyh_flagah_rabotaet_legasi_put(): void
    {
        $uchastniki = new InMemoryWorkspaceParticipantsRepository();
        $newUseCase = $this->dobavitUseCase($uchastniki, 'account-1');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.not_found');

        $legacyRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $legacyRepository->method('getById')->willThrowException(
            new \DomainException('opencode.workspace.not_found'),
        );

        (new DobavitUchastnikaUseCase(
            $legacyRepository,
            $this->createMock(\App\OpenCode\Business\Port\UchastnikiSessiiRepository::class),
            $this->credentials('account-1'),
            $this->projectami(),
            new \App\OpenCode\Business\Utils\ProverkaDostupaKSessii(
                new \App\OpenCode\Tests\Support\InMemoryUchastnikiSessiiRepository(),
            ),
            new WorkspaceFeatureFlags(read: false, write: false),
            $newUseCase,
        ))->handle(new DobavitUchastnika('workspace-1', 'acc-2', 'viewer'));
    }

    private function dobavitUseCase(
        InMemoryWorkspaceParticipantsRepository $uchastniki,
        string $currentAccountId,
    ): DobavitUchastnikaVWorkspaceSessiiUseCase {
        return new DobavitUchastnikaVWorkspaceSessiiUseCase(
            $this->workspaces(),
            $uchastniki,
            $this->credentials($currentAccountId),
            $this->projectami(),
            new ProverkaDostupaWorkspaceSessii($uchastniki),
        );
    }

    private function uchastnik(string $accountId, RolUchastnikaSessii $rol): UchastnikSessii
    {
        return UchastnikSessii::create(
            'workspace-1',
            $accountId,
            $rol,
            null,
            null,
            null,
            FabrikaWorkspace::NOW,
        );
    }

    private function workspaces(): WorkspaceSessionRepository
    {
        $repository = $this->createMock(WorkspaceSessionRepository::class);
        $repository->method('getById')->willReturnCallback(
            static fn (string $id): WorkspaceSession => FabrikaWorkspace::workspaceSession($id),
        );

        return $repository;
    }

    private function projectami(): ImitaciyaUpravleniyaProektamiWorkspace
    {
        $projectami = new ImitaciyaUpravleniyaProektamiWorkspace();
        $projectami->zadatProekty([FabrikaWorkspace::proekt()]);

        return $projectami;
    }

    private function credentials(string $currentAccountId): UmeetProveryatRekvizityOpenCode
    {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn($currentAccountId);
        $credentials->method('trebovatVladelca')->willReturnCallback(static function (): void {
        });

        return $credentials;
    }
}
