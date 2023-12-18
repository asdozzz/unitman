<?php

namespace App\Utils\Model\Reponse;

final class SuccessResponse
{
    public readonly string $status;
    public readonly mixed $data;

    public function __construct(mixed $data)
    {
        $this->data = $data;
        $this->status = ResponseStatus::SUCCESS->value;
    }


}
