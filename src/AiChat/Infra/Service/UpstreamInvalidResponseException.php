<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Service;

/**
 * Upstream вернул не-JSON или слишком большое тело.
 *
 * Сообщение — ключ словаря, контроллер переводит его и отдаёт HTTP 502.
 */
final class UpstreamInvalidResponseException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('ai.chat.upstream_invalid_response');
    }
}
