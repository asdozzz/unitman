<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\Service;

use App\OpenCode\Infra\Service\KanalUvedomleniyOpenCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KanalUvedomleniyOpenCodeTest extends TestCase
{
    private const SECRET = 'test-secret';

    #[Test]
    public function kanal_determiniruet_hmac_sha256_i_prefiks(): void
    {
        $builder = new KanalUvedomleniyOpenCode(self::SECRET);

        $this->assertSame(
            'opencode_notifications#ce8d59ef42493d2e07cb59982ea2c38e41ac84ed2d7376a133d4275627b3e34a',
            $builder->kanalDlyaAccount('acc-1'),
        );
    }

    #[Test]
    public function povtornii_vyzov_daet_tot_zhe_kanal(): void
    {
        $builder = new KanalUvedomleniyOpenCode(self::SECRET);

        $this->assertSame(
            $builder->kanalDlyaAccount('acc-1'),
            $builder->kanalDlyaAccount('acc-1'),
        );
    }

    #[Test]
    public function raznie_akkaunti_poluchayut_raznie_kanali(): void
    {
        $builder = new KanalUvedomleniyOpenCode(self::SECRET);

        $this->assertNotSame(
            $builder->kanalDlyaAccount('acc-1'),
            $builder->kanalDlyaAccount('acc-2'),
        );
    }

    #[Test]
    public function drugaya_sekret_daet_drugoi_kanal(): void
    {
        $pervyi = new KanalUvedomleniyOpenCode(self::SECRET);
        $vtoroi = new KanalUvedomleniyOpenCode('another-secret');

        $this->assertNotSame(
            $pervyi->kanalDlyaAccount('acc-1'),
            $vtoroi->kanalDlyaAccount('acc-1'),
        );
    }
}
