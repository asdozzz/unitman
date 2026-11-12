<?php

declare(strict_types=1);

namespace App\Utils\Exception;

final class Dump extends \Exception
{
    /**
     * @var string[]
     */
    private array $dump = [];

    public function addDump(string $dump): void
    {
        $this->dump[] = $dump;
    }

    public function __toString(): string
    {
        return implode(\PHP_EOL, $this->dump);
    }
}
