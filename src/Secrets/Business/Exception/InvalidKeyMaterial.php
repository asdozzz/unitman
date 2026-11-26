<?php

declare(strict_types=1);

namespace App\Secrets\Business\Exception;

/**
 * Ключевой материал не соответствует контракту (например, AES-256 требует
 * base64 ровно от 32 байт). Паддинг и обрезание не выполняются.
 *
 * Сообщение содержит имя секрета, но никогда — значение.
 */
final class InvalidKeyMaterial extends \DomainException
{
}
