<?php

declare(strict_types=1);

namespace App\Account\Business\Model\OpenCode;

/**
 * Способ передачи идентификатора provider-сессии провайдеру.
 *
 * `Unknown` используется только для будущих провайдеров, когда транспорт
 * явно не описан. Провайдер с таким транспортом недоступен в AI-чате.
 */
enum ProviderSessionTransport: string
{
    case None = 'none';
    case Header = 'header';
    case Body = 'body';
    case Query = 'query';
    case Unknown = 'unknown';
}
