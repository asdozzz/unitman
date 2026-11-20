<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Model;

use App\OpenCode\Business\Model\StatusWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Матрица переходов статусов workspace: домен не должен пропускать
 * несогласованные переходы, даже если фоновая задача ошиблась.
 */
final class StatusWorkspaceTest extends TestCase
{
    #[Test]
    public function matrica_perehodov_sovpadaet_s_kontraktom(): void
    {
        $allowed = [
            StatusWorkspace::CREATING->value => [StatusWorkspace::RUNNING->value, StatusWorkspace::ERROR->value],
            StatusWorkspace::RUNNING->value => [StatusWorkspace::ARCHIVING->value, StatusWorkspace::DELETING->value],
            StatusWorkspace::ARCHIVING->value => [StatusWorkspace::ARCHIVED->value, StatusWorkspace::ERROR->value],
            StatusWorkspace::ARCHIVED->value => [StatusWorkspace::STARTING->value, StatusWorkspace::DELETING->value],
            StatusWorkspace::STARTING->value => [StatusWorkspace::RUNNING->value, StatusWorkspace::ERROR->value],
            StatusWorkspace::ERROR->value => [StatusWorkspace::STARTING->value, StatusWorkspace::DELETING->value],
            StatusWorkspace::DELETING->value => [StatusWorkspace::DELETING->value],
        ];

        foreach (StatusWorkspace::cases() as $from) {
            foreach (StatusWorkspace::cases() as $to) {
                $expected = in_array($to->value, $allowed[$from->value], true);

                $this->assertSame(
                    $expected,
                    $from->mozhnoPerevestiV($to),
                    sprintf('%s -> %s', $from->value, $to->value)
                );
            }
        }
    }

    #[Test]
    public function iz_stroki_vosstanavlivaet_status(): void
    {
        $this->assertSame(StatusWorkspace::RUNNING, StatusWorkspace::izStroki('RUNNING'));
        $this->assertSame(StatusWorkspace::ARCHIVED, StatusWorkspace::izStroki('ARCHIVED'));
    }

    #[Test]
    public function iz_stroki_otklonyaet_neizvestnyi_status(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.invalid_status');

        StatusWorkspace::izStroki('BROKEN');
    }
}
