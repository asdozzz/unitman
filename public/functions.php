<?php

namespace dev;

use App\App\Exception\Dump;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

function dd(...$vars)
{
    $dumper = new HtmlDumper;
    $throwable = new Dump();

    foreach ($vars as $var) {
        $throwable->addDump(
            $dumper->dump((new VarCloner)->cloneVar($var), true)
        );
    }

    throw $throwable;
}
