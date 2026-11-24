<?php

namespace App\Runner\Business\Command;

final class RemoveProjectCommand
{
    public function __construct(
        public readonly string $ProjectId,
        public readonly ?string $RunnerId = null,
        public readonly ?string $TaskQueue = null,
    ) {

    }
 }
