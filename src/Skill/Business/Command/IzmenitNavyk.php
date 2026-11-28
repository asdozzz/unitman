<?php

declare(strict_types=1);

namespace App\Skill\Business\Command;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Частичное изменение навыка: `null` означает «оставить как есть».
 */
final class IzmenitNavyk
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $id = '',
        #[Assert\Length(max: 128)]
        public readonly ?string $name = null,
        #[Assert\Length(max: 512)]
        public readonly ?string $description = null,
        public readonly ?string $content = null,
    ) {
    }
}
