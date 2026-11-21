<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\Project\Event;

final class ZashishennieVetkiProektaUstanovleni
{
    /**
     * @param string[] $zashishennieVetki
     */
    public function __construct(
        public readonly string $id,
        public readonly array $zashishennieVetki = []
    )
    {
    }
}
