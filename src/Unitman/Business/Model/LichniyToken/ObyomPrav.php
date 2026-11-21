<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\LichniyToken;

/**
 * Объём прав личного токена:
 * - `vse` — любой проект Repo, включая будущие;
 * - `spisok` — только проекты из прикреплённого списка.
 */
enum ObyomPrav: string
{
    case Vse = 'vse';
    case Spisok = 'spisok';
}
