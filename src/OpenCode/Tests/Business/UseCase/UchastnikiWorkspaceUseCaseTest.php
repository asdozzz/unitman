<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\DobavitUchastnika;
use App\OpenCode\Business\Command\IzmenitUchastnika;
use App\OpenCode\Business\Command\PoluchitUchastnikov;
use App\OpenCode\Business\Command\UdalitUchastnika;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\UseCase\DobavitUchastnikaUseCase;
use App\OpenCode\Business\UseCase\IzmenitUchastnikaUseCase;
use App\OpenCode\Business\UseCase\PoluchitUchastnikovQuery;
use App\OpenCode\Business\UseCase\UdalitUchastnikaUseCase;
use App\OpenCode\Business\Utils\ProverkaDostupaKSessii;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use App\OpenCode\Tests\Support\ImitaciyaUpravleniyaProektamiWorkspace;
use App\OpenCode\Tests\Support\InMemoryUchastnikiSessiiRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UchastnikiWorkspaceUseCaseTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function vladelec_dobavlyaet_writer_s_dostupom_k_proektam(): void
    {
        $uchastniki = $this->sRepozitoriem();
        $useCase = $this->dobavitUseCase($uchastniki, 'owner-1');

        $useCase->handle(new DobavitUchastnika(
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
        $this->assertSame('owner-1', $uchastnik->getInvitedBy());
    }

    #[Test]
    public function writer_ne_mozhet_priqlashat_uchastnikov(): void
    {
        $uchastniki = $this->sRepozitoriem([
            $this->uchastnik('acc-2', RolUchastnikaSessii::WRITER),
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.forbidden');

        $this->dobavitUseCase($uchastniki, 'acc-2')->handle(new DobavitUchastnika(
            'workspace-1',
            'acc-3',
            'viewer',
        ));
    }

    #[Test]
    public function uchastnik_bez_dostupa_k_proektu_ne_priqlashaetsya(): void
    {
        $uchastniki = $this->sRepozitoriem();
        $projectami = $this->projectami();
        $projectami->zapretitDostup('acc-2');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.participant.project_access_denied');

        (new DobavitUchastnikaUseCase(
            $this->sessii(),
            $uchastniki,
            $this->credentials('owner-1'),
            $projectami,
            new ProverkaDostupaKSessii($uchastniki),
        ))->handle(new DobavitUchastnika('workspace-1', 'acc-2', 'viewer'));
    }

    #[Test]
    public function vladelca_nelzya_dobavit_uchastnikom(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.participant.already_owner');

        $this->dobavitUseCase($this->sRepozitoriem(), 'owner-1')
            ->handle(new DobavitUchastnika('workspace-1', 'owner-1', 'viewer'));
    }

    #[Test]
    public function roli_owner_cherez_priqlashenie_ne_vydayut(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.participant.owner_role_immutable');

        $this->dobavitUseCase($this->sRepozitoriem(), 'owner-1')
            ->handle(new DobavitUchastnika('workspace-1', 'acc-2', 'owner'));
    }

    #[Test]
    public function chuzhie_rekvizity_kandidatu_ne_prikreplyayutsya(): void
    {
        $credentials = $this->credentials('owner-1');
        $credentials->expects($this->once())
            ->method('trebovatVladelca')
            ->with('acc-2', 'cred-chuzhoy')
            ->willThrowException(new \DomainException('account.opencode_credential.not_found'));

        $this->expectException(\DomainException::class);

        (new DobavitUchastnikaUseCase(
            $this->sessii(),
            $this->sRepozitoriem(),
            $credentials,
            $this->projectami(),
            new ProverkaDostupaKSessii($this->sRepozitoriem()),
        ))->handle(new DobavitUchastnika('workspace-1', 'acc-2', 'writer', 'cred-chuzhoy'));
    }

    #[Test]
    public function spisok_pokazyvaet_nevyavnogo_vladelca_bez_stroki_uchastnika(): void
    {
        $uchastniki = $this->sRepozitoriem();

        $items = (new PoluchitUchastnikovQuery(
            $this->sessii(),
            $uchastniki,
            $this->credentials('owner-1'),
            new ProverkaDostupaKSessii($uchastniki),
        ))->handle(new PoluchitUchastnikov('workspace-1'));

        $this->assertCount(1, $items);
        $this->assertSame('owner-1', $items[0]->accountId);
        $this->assertTrue($items[0]->vladelec);
        $this->assertSame('credential-1', $items[0]->defaultCredentialId);
    }

    #[Test]
    public function vladelca_nelzya_ponizit_ili_udalit(): void
    {
        $uchastniki = $this->sRepozitoriem([
            $this->uchastnik('owner-1', RolUchastnikaSessii::OWNER),
        ]);

        try {
            $this->izmenitUseCase($uchastniki)->handle(
                new IzmenitUchastnika('workspace-1', 'owner-1', 'viewer'),
            );
            $this->fail('Ожидалась ошибка неизменяемой роли владельца');
        } catch (\DomainException $exception) {
            $this->assertSame('opencode.participant.owner_role_immutable', $exception->getMessage());
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.participant.owner_cannot_be_removed');

        $this->udalitUseCase($uchastniki)->handle(new UdalitUchastnika('workspace-1', 'owner-1'));
    }

    #[Test]
    public function vladelec_udalyaet_writer(): void
    {
        $uchastniki = $this->sRepozitoriem([
            $this->uchastnik('owner-1', RolUchastnikaSessii::OWNER),
            $this->uchastnik('acc-2', RolUchastnikaSessii::WRITER),
        ]);

        $this->udalitUseCase($uchastniki)->handle(new UdalitUchastnika('workspace-1', 'acc-2'));

        $this->assertNull($uchastniki->find('workspace-1', 'acc-2'));
        $this->assertSame('acc-2', $uchastniki->udalennyi?->getAccountId());
    }

    #[Test]
    public function neuchastnik_ne_vidit_spisok(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.not_found');

        (new PoluchitUchastnikovQuery(
            $this->sessii(),
            $this->sRepozitoriem(),
            $this->credentials('acc-3'),
            new ProverkaDostupaKSessii($this->sRepozitoriem()),
        ))->handle(new PoluchitUchastnikov('workspace-1'));
    }

    /**
     * @param list<UchastnikSessii> $uchastniki
     */
    private function sRepozitoriem(array $uchastniki = []): InMemoryUchastnikiSessiiRepository
    {
        $repository = new InMemoryUchastnikiSessiiRepository();

        foreach ($uchastniki as $uchastnik) {
            $repository->save($uchastnik);
        }

        return $repository;
    }

    private function sessii(): SessiyaOpenCodeRepository
    {
        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn($this->sessiya());

        return $repository;
    }

    private function sessiya(): SessiyaOpenCode
    {
        return SessiyaOpenCode::createWorkspace(
            'workspace-1',
            'owner-1',
            'credential-1',
            'payments-refactor',
            'main',
            [FabrikaWorkspace::proekt()],
            'Первый промпт',
            self::NOW,
            'model-session',
        );
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
        $credentials->method('trebovatVladelca')->willReturnCallback(
            static function (string $accountId, string $credentialId): void {
                if ($credentialId === 'cred-chuzhoy') {
                    throw new \DomainException('account.opencode_credential.not_found');
                }
            }
        );

        return $credentials;
    }

    private function dobavitUseCase(
        InMemoryUchastnikiSessiiRepository $uchastniki,
        string $currentAccountId,
    ): DobavitUchastnikaUseCase {
        return new DobavitUchastnikaUseCase(
            $this->sessii(),
            $uchastniki,
            $this->credentials($currentAccountId),
            $this->projectami(),
            new ProverkaDostupaKSessii($uchastniki),
        );
    }

    private function izmenitUseCase(InMemoryUchastnikiSessiiRepository $uchastniki): IzmenitUchastnikaUseCase
    {
        return new IzmenitUchastnikaUseCase(
            $this->sessii(),
            $uchastniki,
            $this->credentials('owner-1'),
            new ProverkaDostupaKSessii($uchastniki),
        );
    }

    private function udalitUseCase(InMemoryUchastnikiSessiiRepository $uchastniki): UdalitUchastnikaUseCase
    {
        return new UdalitUchastnikaUseCase(
            $this->sessii(),
            $uchastniki,
            $this->credentials('owner-1'),
            new ProverkaDostupaKSessii($uchastniki),
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
            'owner-1',
            self::NOW,
        );
    }
}
