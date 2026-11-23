<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Model;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\ReadModel\HodOpenCodeReadModel;
use App\OpenCode\Business\ReadModel\SessiyaOpenCodeReadModel;
use App\OpenCode\Business\UnitSession\Model\UnitSession;
use App\OpenCode\Business\UnitSession\ReadModel\UnitSessionReadModel;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Модель хранится на агрегатах сессий и ходов и отдаётся в read-моделях.
 */
final class ModelOpenCodeTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function sessiya_hranit_model_iz_zaprosa(): void
    {
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', null, [], self::NOW, 'gpt-4o');

        self::assertSame('gpt-4o', $sessiya->getModel());
    }

    #[Test]
    public function sessiya_bez_modeli_hranit_null(): void
    {
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', null, [], self::NOW);

        self::assertNull($sessiya->getModel());
    }

    #[Test]
    public function sessiya_vosstanavlivaet_model(): void
    {
        $sessiya = SessiyaOpenCode::restore(
            's-1',
            'acc-1',
            'cred-1',
            'Заголовок',
            null,
            [],
            null,
            null,
            self::NOW,
            self::NOW,
            'claude-3-5-sonnet',
        );

        self::assertSame('claude-3-5-sonnet', $sessiya->getModel());
    }

    #[Test]
    public function workspace_hranit_model(): void
    {
        $sessiya = SessiyaOpenCode::createWorkspace(
            'w-1',
            'acc-1',
            'cred-1',
            'ws',
            'main',
            [new ProektWorkspace('p-1', 'api', 'services/api', true, 0, 'main')],
            'prompt',
            self::NOW,
            'gpt-4o-mini',
        );

        self::assertSame('gpt-4o-mini', $sessiya->getModel());
    }

    #[Test]
    public function unit_session_hranit_model(): void
    {
        $session = UnitSession::create('u-1', 'acc-1', 'cred-1', null, 'unit-1', 'p-1', self::NOW, 'gpt-4o');

        self::assertSame('gpt-4o', $session->getModel());
    }

    #[Test]
    public function workspace_session_hranit_model(): void
    {
        $session = WorkspaceSession::create(
            'w-1',
            'acc-1',
            'cred-1',
            'ws',
            'main',
            [new ProektWorkspace('p-1', 'api', 'services/api', true, 0, 'main')],
            'prompt',
            self::NOW,
            'gpt-4o',
        );

        self::assertSame('gpt-4o', $session->getModel());
    }

    #[Test]
    public function hod_hranit_model_pri_sozdanii_i_vosstanovlenii(): void
    {
        $hod = HodOpenCode::create('t-1', 's-1', 1, 'wf-1', 'Привет', [], self::NOW, 'gpt-4o');
        self::assertSame('gpt-4o', $hod->getModel());
        self::assertSame('gpt-4o', $hod->toArray()['model']);

        $vosstanovlennyi = HodOpenCode::restore(
            't-1',
            's-1',
            1,
            'wf-1',
            'RUNNING',
            'Привет',
            [],
            null,
            null,
            self::NOW,
            null,
            'gpt-4o',
        );
        self::assertSame('gpt-4o', $vosstanovlennyi->getModel());
    }

    #[Test]
    public function read_model_hoda_otdayot_model(): void
    {
        $hod = HodOpenCode::create('t-1', 's-1', 1, 'wf-1', 'Привет', [], self::NOW, 'gpt-4o');

        $readModel = HodOpenCodeReadModel::izModela($hod, 'channel');

        self::assertSame('gpt-4o', $readModel->model);
        self::assertSame('gpt-4o', $readModel->toArray()['model']);
    }

    #[Test]
    public function read_model_sessii_otdayot_model(): void
    {
        $sessiya = SessiyaOpenCode::create(
            's-1',
            'acc-1',
            'cred-1',
            'Заголовок',
            [PrivyazkaKUnitu::izZnacheniy('unit-1', 'p-1')],
            self::NOW,
            'gpt-4o',
        );

        $readModel = SessiyaOpenCodeReadModel::izModela($sessiya);

        self::assertSame('gpt-4o', $readModel->model);
    }

    #[Test]
    public function read_model_unit_sessii_otdayot_model(): void
    {
        $session = UnitSession::create('u-1', 'acc-1', 'cred-1', null, 'unit-1', 'p-1', self::NOW, 'gpt-4o');

        $readModel = UnitSessionReadModel::izModela($session);

        self::assertSame('gpt-4o', $readModel->model);
        self::assertSame('gpt-4o', $readModel->toArray()['model']);
    }
}
