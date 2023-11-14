<?php

namespace App\Utils\Model\Reponse;

final class ErrorResponse
{
    public readonly string $status;
    public readonly string $message;

    public function __construct(string $message)
    {
        $this->status = ResponseStatus::ERROR->value;
        $this->message = $message;
    }
}
