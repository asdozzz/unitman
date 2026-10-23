<?php

namespace App\Unitman\Business\Model\Unit\ConfigUnita;

final class KonfigStadiiSborki
{
    const DEFAULT_IMAGE = 'asdozzz/unitman-unit:latest';

    public function __construct(
        public readonly string $obraz,
        public readonly array $dopolnitelnieKomandi
    )
    {
    }

    function toArray(): array
    {
        return [
            'image' => $this->obraz,
            'commands' => $this->dopolnitelnieKomandi
        ];
    }

    static function makeDefault(): self
    {
        return new self(self::DEFAULT_IMAGE, []);
    }

    static function fromArray(array $cfg): self
    {
        return new self($cfg['image'] ?? self::DEFAULT_IMAGE, $cfg['commands'] ?? []);
    }
}
