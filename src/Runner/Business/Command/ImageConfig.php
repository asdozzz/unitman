<?php

namespace App\Runner\Business\Command;

readonly class ImageConfig
{
    /**
     * @params string[] $Commands
     * */
    public function __construct(public string $Image, public array $Commands)
    {
    }

}
