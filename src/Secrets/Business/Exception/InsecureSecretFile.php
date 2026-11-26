<?php

declare(strict_types=1);

namespace App\Secrets\Business\Exception;

/**
 * Файл секрета доступен группе или всем. Требование — режим 0600.
 */
final class InsecureSecretFile extends \DomainException
{
    public static function forFile(string $name): self
    {
        return new self(sprintf(
            'Secret file "%s" must be mode 0600 (not group/world readable or writable)',
            $name,
        ));
    }
}
