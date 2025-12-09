<?php

namespace App\Unitman\Business\Model\Unit\UnitProcess;

enum UnitProcessState: string
{
    case NEW = 'NEW';
    case ZADACHI_DOBAVLENI = 'ZADACHI_DOBAVLENI';
    case PENDING = 'PENDING';
    case SUCCESS = 'SUCCESS';
    case ERROR = 'ERROR';
    case CANCLED = 'CANCLED';
}
