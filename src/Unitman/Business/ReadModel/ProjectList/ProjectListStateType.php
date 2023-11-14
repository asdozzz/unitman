<?php

namespace App\Unitman\Business\ReadModel\ProjectList;

enum ProjectListStateType: string
{
    case NEW= 'NEW';
    case BUILD_PENDING= 'BUILD_PENDING';
    case BUILD_SUCCESS= 'BUILD_SUCCESS';
    case BUILD_ERROR= 'BUILD_ERROR';

    case REMOVE_PENDING= 'REMOVE_PENDING';
    case REMOVE_ERROR= 'REMOVE_ERROR';

}
