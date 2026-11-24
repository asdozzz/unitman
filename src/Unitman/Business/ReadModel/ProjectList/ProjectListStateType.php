<?php

namespace App\Unitman\Business\ReadModel\ProjectList;

enum ProjectListStateType: string
{
    case NEW= 'NEW';

    case REMOVE_PENDING= 'REMOVE_PENDING';
    case REMOVE_ERROR= 'REMOVE_ERROR';

}
