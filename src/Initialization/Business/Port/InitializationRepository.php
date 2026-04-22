<?php

namespace App\Initialization\Business\Port;

use App\Initialization\Business\Model\InitializationRecord;

interface InitializationRepository
{
    public function getByProp(string $prop): InitializationRecord;

    public function save(InitializationRecord $record): void;

    public function getAll(): array;
}
