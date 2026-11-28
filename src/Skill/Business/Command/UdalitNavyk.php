<?php

declare(strict_types=1);

namespace App\Skill\Business\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class UdalitNavyk
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $id = '',
    ) {
    }
}
