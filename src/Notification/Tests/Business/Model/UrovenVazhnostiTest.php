<?php

declare(strict_types=1);

namespace App\Notification\Tests\Business\Model;

use App\Notification\Business\Model\UrovenVazhnosti;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UrovenVazhnostiTest extends TestCase
{
    #[Test]
    public function info_ne_trebuet_prochteniya(): void
    {
        self::assertFalse(UrovenVazhnosti::INFO->trebueProchteniya());
    }

    #[Test]
    public function attention_trebuet_prochteniya(): void
    {
        self::assertTrue(UrovenVazhnosti::ATTENTION->trebueProchteniya());
    }

    #[Test]
    public function critical_trebuet_prochteniya(): void
    {
        self::assertTrue(UrovenVazhnosti::CRITICAL->trebueProchteniya());
    }
}
