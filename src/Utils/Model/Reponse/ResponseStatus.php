<?php

namespace App\Utils\Model\Reponse;

enum ResponseStatus: string
{
    case SUCCESS = 'success';
    case FAIL = 'fail';
    case ERROR = 'error';
}
