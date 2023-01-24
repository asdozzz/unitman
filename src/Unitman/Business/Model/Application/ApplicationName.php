<?php

namespace App\Unitman\Business\Model\Application;

final class ApplicationName
{
    private string $name;

    public function __construct(string $name)
    {
        if (empty($name)) {
            throw new \Exception('app.app_name.is_empty');
        }
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


}
