<?php

declare(strict_types=1);

namespace App\AiChat\Business\Model;

/**
 * Причина архивации AI-сессии.
 *
 * `manual` — пользователь архивировал вручную; `expired` — истёк TTL
 * пользовательской сессии; `credential_deleted` — удалены реквизиты
 * провайдера, к которым была привязана сессия.
 */
enum PrichinaArhivaciiSessiiAiChata: string
{
    case Manual = 'manual';
    case Expired = 'expired';
    case CredentialDeleted = 'credential_deleted';
}
