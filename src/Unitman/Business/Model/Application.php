<?php

namespace App\Unitman\Business\Model;

use App\Unitman\Business\Model\Application\ApplicationId;
use App\Unitman\Business\Model\Application\ApplicationName;

final class Application
{
    public function __construct(private ApplicationId $id, private ApplicationName $name)
    {

    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id->toString();
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name->getName();
    }


}
