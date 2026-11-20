<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Тип сессии OpenCode: обычная сессия юнита или workspace-сессия.
 */
enum TipSessiiOpenCode: string
{
    case UNIT = 'unit';
    case WORKSPACE = 'workspace';
}
