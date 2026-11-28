<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitSsylkuWorkspace;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\UseCase\PoluchitSsylkuWorkspaceQuery;
use App\OpenCode\Business\Utils\ProverkaDostupaKSessii;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use App\OpenCode\Tests\Support\ImitaciyaUpravleniyaProektamiWorkspace;
use App\OpenCode\Tests\Support\InMemoryUchastnikiSessiiRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Ссылка IDE участникам workspace: viewer получает read-only адрес,
 * не-участник — `opencode.session.not_found`, владелец — как раньше.
 */
final class PoluchitSsylkuWorkspaceQueryTest extends TestCase
{
    private const PROJECT_LINK = 'https://47000.payments-refactor-workspac.workspaces.example.test';

    private const WORKSPACE_LINK = 'http://47000.payments-refactor-workspac.workspaces.example.test';

    #[Test]
    public function vladelec_poluchaet_ssylku_kak_ranshe(): void
    {
        $holder = $this->query('account-1', $this->sRepozitoriem())
            ->handle(new PoluchitSsylkuWorkspace('workspace-1', 47000));

        $this->assertSame(self::WORKSPACE_LINK, $holder->toArray()['link']);
    }

    #[Test]
    public function viewer_poluchaet_ssylku_ide(): void
    {
        $uchastniki = $this->sRepozitoriem([
            $this->uchastnik('account-2', RolUchastnikaSessii::VIEWER),
        ]);

        $holder = $this->query('account-2', $uchastniki)
            ->handle(new PoluchitSsylkuWorkspace('workspace-1', 47000));

        $this->assertSame(self::WORKSPACE_LINK, $holder->toArray()['link']);
    }

    #[Test]
    public function writer_poluchaet_ssylku_ide(): void
    {
        $uchastniki = $this->sRepozitoriem([
            $this->uchastnik('account-2', RolUchastnikaSessii::WRITER),
        ]);

        $holder = $this->query('account-2', $uchastniki)
            ->handle(new PoluchitSsylkuWorkspace('workspace-1', 47000));

        $this->assertSame(self::WORKSPACE_LINK, $holder->toArray()['link']);
    }

    #[Test]
    public function neuchastnik_ne_poluchaet_ssylku(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.not_found');

        $this->query('account-3', $this->sRepozitoriem())
            ->handle(new PoluchitSsylkuWorkspace('workspace-1', 47000));
    }

    #[Test]
    public function nedostupnaya_ssylka_ostayotsya_oshibkoy(): void
    {
        $projectami = $this->projectami();
        $projectami->zadatSsylkuRedaktora('project-1', null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.link_unavailable');

        (new PoluchitSsylkuWorkspaceQuery(
            $this->sessii(),
            $this->credentials('account-1'),
            $projectami,
            new ProverkaDostupaKSessii($this->sRepozitoriem()),
        ))->handle(new PoluchitSsylkuWorkspace('workspace-1', 47000));
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

    private function query(string $currentAccountId, InMemoryUchastnikiSessiiRepository $uchastniki): PoluchitSsylkuWorkspaceQuery
    {
        return new PoluchitSsylkuWorkspaceQuery(
            $this->sessii(),
            $this->credentials($currentAccountId),
            $this->projectami(),
            new ProverkaDostupaKSessii($uchastniki),
        );
    }

    private function sessii(): SessiyaOpenCodeRepository
    {
        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn($this->sessiya());

        return $repository;
    }

    private function sessiya(): SessiyaOpenCode
    {
        return FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);
    }

    private function projectami(): ImitaciyaUpravleniyaProektamiWorkspace
    {
        $projectami = new ImitaciyaUpravleniyaProektamiWorkspace();
        $projectami->zadatProekty([FabrikaWorkspace::proekt()]);
        $projectami->zadatSsylkuRedaktora('project-1', self::PROJECT_LINK);

        return $projectami;
    }

    private function credentials(string $currentAccountId): UmeetProveryatRekvizityOpenCode
    {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn($currentAccountId);

        return $credentials;
    }

    private function uchastnik(string $accountId, RolUchastnikaSessii $rol): UchastnikSessii
    {
        return UchastnikSessii::create(
            'workspace-1',
            $accountId,
            $rol,
            null,
            null,
            'account-1',
            FabrikaWorkspace::NOW,
        );
    }
}
