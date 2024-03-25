<?php

namespace App\Utils\Model\Reponse;

use App\Utils\Model\Reponse\ErrorResponse\ErrorCodeEnum;

final class ErrorResponse
{
    public readonly string $status;
    public readonly string $message;
    public readonly string $code;

    public function __construct(string $message, ErrorCodeEnum $code)
    {
        $this->status = ResponseStatus::ERROR->value;
        $this->message = $message;
        $this->code = $code->value;
    }
}
