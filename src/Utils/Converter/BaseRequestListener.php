<?php

namespace App\Utils\Converter;

use App\Utils\Model\Reponse\ErrorResponse\ErrorCodeEnum;
use App\Utils\Service\LockService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(event: RequestEvent::class, method: 'onKernelRequest')]
final class BaseRequestListener
{
    public function __construct(private LockService $lockService)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($this->lockService->isLock()) {
            $lockContent = $this->lockService->getLockContent();
            $response = new JsonResponse(\App\Utils\Model\Reponse\Response::error($lockContent, ErrorCodeEnum::LOCK));
            $event->setResponse($response);
            return;
        }
    }

}
