<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Service;

/**
 * Транспортная ошибка при обращении к OpenAI-совместимому API.
 *
 * Сообщение — ключ словаря, контроллер переводит его и отдаёт HTTP 502.
 */
final class UpstreamUnavailableException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('ai.chat.upstream_unavailable');
    }
}
