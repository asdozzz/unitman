<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\ReadModel;

use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UvedomlenieONovomSoobsheniiOpenCodeTest extends TestCase
{
    #[Test]
    public function to_array_soderzhit_tochnii_nabor_i_poryadok_klyuchei(): void
    {
        $notification = new UvedomlenieONovomSoobsheniiOpenCode(
            'n-1',
            'acc-1',
            's-1',
            't-1',
            'Название сессии',
            'ANSWERED',
            'ANSWER',
            1_700_000_000,
        );

        $payload = $notification->toArray();

        $this->assertSame(
            [
                'eventType',
                'id',
                'sessionId',
                'sessionType',
                'turnId',
                'sessionTitle',
                'status',
                'kind',
                'createdAt',
            ],
            array_keys($payload),
        );

        $this->assertSame('new_message', $payload['eventType']);
        $this->assertSame('n-1', $payload['id']);
        $this->assertSame('s-1', $payload['sessionId']);
        $this->assertSame('unit', $payload['sessionType']);
        $this->assertSame('t-1', $payload['turnId']);
        $this->assertSame('Название сессии', $payload['sessionTitle']);
        $this->assertSame('ANSWERED', $payload['status']);
        $this->assertSame('ANSWER', $payload['kind']);
        $this->assertSame(1_700_000_000, $payload['createdAt']);
    }

    #[Test]
    public function session_type_workspace_popadaet_v_publikaciyu(): void
    {
        $notification = new UvedomlenieONovomSoobsheniiOpenCode(
            'n-2',
            'acc-1',
            'w-1',
            't-2',
            'payments-refactor',
            'ANSWERED',
            'ANSWER',
            1_700_000_001,
            'workspace',
        );

        $this->assertSame('workspace', $notification->toArray()['sessionType']);
    }

    #[Test]
    public function account_id_ne_popadaet_v_publikaciyu(): void
    {
        $notification = new UvedomlenieONovomSoobsheniiOpenCode(
            'n-1',
            'acc-1',
            's-1',
            't-1',
            '',
            'FAILED',
            null,
            1_700_000_000,
        );

        $this->assertArrayNotHasKey('accountId', $notification->toArray());
        $this->assertNull($notification->toArray()['kind']);
    }
}
