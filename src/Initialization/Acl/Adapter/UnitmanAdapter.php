<?php

namespace App\Initialization\Acl\Adapter;

use App\Initialization\Business\Port\UnitmanPort;
use App\Unitman\Api\UnitmanApi;

final class UnitmanAdapter implements UnitmanPort
{
    public function __construct(private UnitmanApi $unitmanApi)
    {
    }

    function proveritProxyHost(?string $proxyHost): bool
    {
        return $this->unitmanApi->proveritProxyHost($proxyHost);
    }
}
