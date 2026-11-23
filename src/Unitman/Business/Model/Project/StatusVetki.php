<?php

namespace App\Unitman\Business\Model\Project;

/**
 * Состояние ветки в Git-провайдере, важное для polling.
 *
 * `UNKNOWN` — провайдер не дал надёжного ответа. Это не повод удалять юнит:
 * polling должен пропустить ветку и повторить попытку позже.
 */
enum StatusVetki: string
{
    case ACTIVE = 'ACTIVE';
    case MERGED = 'MERGED';
    case DELETED = 'DELETED';
    case UNKNOWN = 'UNKNOWN';
}
