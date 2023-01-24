<?php

namespace App\Unitman\Business\Model\Application;

final class ApplicationId
{
    private string $id;

    public function __construct(string $id)
    {
        if (empty($id)) {
            throw new \Exception('app.app_id.is_empty');
        }

        $this->id = $id;
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        return $this->id;
    }


}
