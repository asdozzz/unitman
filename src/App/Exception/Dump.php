<?php

namespace App\App\Exception;

class Dump extends \Exception {

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
