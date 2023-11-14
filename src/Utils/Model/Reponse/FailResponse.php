<?php

namespace App\Utils\Model\Reponse;

final class FailResponse
{
    public readonly string $status;
    public readonly mixed $data;

    public function __construct(mixed $data)
    {
        $this->status = ResponseStatus::FAIL->value;
        $this->data = $data;
    }
}
