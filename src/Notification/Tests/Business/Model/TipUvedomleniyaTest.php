<?php

declare(strict_types=1);

namespace App\Notification\Tests\Business\Model;

use App\Notification\Business\Model\TipUvedomleniya;
use App\Notification\Business\Model\UrovenVazhnosti;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TipUvedomleniyaTest extends TestCase
{
    #[Test]
    public function zapros_aktivacii_sistemniy_i_dlya_administratorov(): void
    {
        $type = TipUvedomleniya::ACCOUNT_ACTIVATION_REQUEST;

        $this->assertTrue($type->sistemniy());
        $this->assertTrue($type->dlyaAdministratorov());
        $this->assertSame(UrovenVazhnosti::ATTENTION, $type->vazhnostPoUmolchaniyu());
    }

    #[Test]
    public function zapros_aktivacii_vhodit_v_spisok_tipov(): void
    {
        $this->assertContains(
            TipUvedomleniya::ACCOUNT_ACTIVATION_REQUEST,
            TipUvedomleniya::vse(),
        );
    }
}
