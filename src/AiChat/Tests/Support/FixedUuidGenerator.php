<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Support;

use App\AiChat\Business\Port\UuidGenerator;

/**
 * Детерминированный генератор UUID для тестов.
 *
 * Возвращает заранее заданные значения по очереди, затем — `uuid-N`.
 */
final class FixedUuidGenerator implements UuidGenerator
{
    /**
     * @var list<string>
     */
    private array $ochered;

    private int $schetchik = 0;

    public function __construct(string ...$znacheniya)
    {
        $this->ochered = $znacheniya;
    }

    public function generit(): string
    {
        if ($this->ochered !== []) {
            return (string) array_shift($this->ochered);
        }

        ++$this->schetchik;

        return 'uuid-'.$this->schetchik;
    }
}
