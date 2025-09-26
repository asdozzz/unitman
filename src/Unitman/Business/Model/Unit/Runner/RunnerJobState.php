<?php

namespace App\Unitman\Business\Model\Unit\Runner;

enum RunnerJobState: string
{
    CASE NEW = 'NEW';
    CASE PENDING = 'PENDING';
    CASE CANCLED = 'CANCLED';
    CASE SUCCESS = 'SUCCESS';
    CASE ERROR = 'ERROR';
}
