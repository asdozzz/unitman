<?php

declare(strict_types=1);

namespace App\Skill\Business\Command;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Создание навыка.
 *
 * `scope` — `global` или `project`. Для проектного навыка `projectIds`
 * обязателен, для глобального — запрещён.
 */
final class SozdatNavyk
{
    /**
     * @param list<string> $projectIds
     */
    public function __construct(
        #[Assert\Length(max: 64)]
        public readonly ?string $slug = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 128)]
        public readonly string $name = '',
        #[Assert\Length(max: 512)]
        public readonly string $description = '',
        public readonly string $content = '',
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['global', 'project'])]
        public readonly string $scope = 'global',
        #[Assert\All([new Assert\Type('string')])]
        public readonly array $projectIds = [],
    ) {
    }
}
