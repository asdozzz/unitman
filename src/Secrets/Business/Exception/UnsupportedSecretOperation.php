<?php

declare(strict_types=1);

namespace App\Secrets\Business\Exception;

/**
 * Бэкенд не умеет выполнять операцию (например, env-переменные нельзя
 * ротировать из приложения). Никогда не имитирует успех.
 */
final class UnsupportedSecretOperation extends \DomainException
{
}
