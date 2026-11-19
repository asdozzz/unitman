<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha;

/**
 * Ошибка разбора тела вебхука.
 *
 * Сообщение — стабильный код (`SobitiePayloadDecoder::ERROR_*`), а не текст для
 * клиента: активность ловит это исключение, пишет предупреждение в лог и
 * возвращает код, чтобы задание RoadRunner не ушло в retry-цикл.
 */
final class SobitiePayloadException extends \RuntimeException
{
}
