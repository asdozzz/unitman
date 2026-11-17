<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\Unit;

/**
 * Кто именно держит юнит.
 *
 * Значение `PROZES` не используется реестром `unit_zanyatost`: занятость
 * собственными процессами юнита вычисляется по агрегату и не требует записи
 * в таблицу. Значение оставлено, чтобы внешние модули могли различать
 * причины в одном перечислении, не зная о внутреннем устройстве Unitman.
 */
enum TipZanyatosti: string
{
    case OPENCODE_HOD = 'opencode_hod';
    case PROZES = 'prozes';
}
