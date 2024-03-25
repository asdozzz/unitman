<?php

namespace App\Utils\Model\Reponse\ErrorResponse;

enum ErrorCodeEnum: string
{
    case DEFAULT = 'DEFAULT';
    case LOCK = 'LOCK';
}
