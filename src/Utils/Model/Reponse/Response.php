<?php

namespace App\Utils\Model\Reponse;

use App\Utils\Model\Reponse\ErrorResponse\ErrorCodeEnum;

final class Response
{
    public static function success(mixed $data): SuccessResponse
    {
        return new SuccessResponse($data);
    }

    public static function successStub(): SuccessResponse
    {
        return new SuccessResponse(null);
    }

    public static function fail(mixed $data): FailResponse
    {
        return new FailResponse($data);
    }

    public static function error(string $message, ErrorCodeEnum $code = ErrorCodeEnum::DEFAULT): ErrorResponse
    {
        return new ErrorResponse($message, $code);
    }
}
