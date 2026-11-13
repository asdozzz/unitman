<?php

declare(strict_types=1);

namespace App\Utils\EventSauce\Model;

/**
 * Итог попытки дочитать проекцию.
 *
 * Раньше метод возвращал bool, и вызывающий код трактовал false как ошибку
 * («background job returned false»). Из-за этого занятая другим процессом
 * блокировка попадала в предохранитель перезапусков и останавливала сервис,
 * хотя работы просто не было.
 */
enum ProjectionPullResult
{
    /** Проекция дочитана до актуального checkpoint. */
    case Pulled;

    /** Блокировку держит другой процесс: работу выполнит он. */
    case Skipped;
}
