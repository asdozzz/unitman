<?php

declare(strict_types=1);

namespace App\Skill\Business\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class PrikrepitNavykKProektam
{
    /**
     * @param list<string> $projectIds
     */
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $id = '',
        #[Assert\Count(min: 1)]
        #[Assert\All([new Assert\Type('string')])]
        public readonly array $projectIds = [],
    ) {
    }
}
