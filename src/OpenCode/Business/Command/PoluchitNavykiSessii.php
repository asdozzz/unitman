<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Эффективные навыки для сессии OpenCode.
 *
 * Клиент передаёт только идентификатор сессии; проекты определяются на
 * сервере из самой сессии.
 */
final class PoluchitNavykiSessii
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $sessionId = '',
    ) {
    }
}
