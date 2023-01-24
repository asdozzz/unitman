<?php

namespace App\Unitman\Business\Command;

final class AddApplicationCommand
{
    public function __construct(private string $name)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


}
