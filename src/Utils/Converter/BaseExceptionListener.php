<?php
/**
 * Created by PhpStorm.
 * User: asd
 * Date: 10.07.2019
 * Time: 20:28
 */

namespace App\Utils\Converter;

use App\App\Exception\Dump;
use \Exception;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Class BaseExceptionListener
 *
 */
#[AsEventListener(event: ExceptionEvent::class, method: 'onKernelException')]
class BaseExceptionListener
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @param ExceptionEvent $event
     *
     * @throws Exception
     *
     * @return true
     */
    public function onKernelException(ExceptionEvent $event): bool
    {
        $exception = $event->getThrowable();

        $response = match ($exception::class) {
            Dump::class => $this->dump($event),
            \DomainException::class => $this->makeFail($exception),
            default => $this->makeError($exception)
        };

        $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        $event->setResponse($response);

        return true;

    }//end onKernelException()

    function dump(ExceptionEvent $event): Response
    {
        return new Response((string) $event->getThrowable(), 200);
    }


    function makeFail(\Throwable $exception): JsonResponse
    {
        $message = $exception->getMessage();
        $this->translator->trans($message);
        return new JsonResponse(\App\Utils\Model\Reponse\Response::fail(['message' => $message]));
    }

    function makeError(\Throwable $exception): JsonResponse
    {
        $message = $exception->getMessage();
        $this->translator->trans($message);
        return new JsonResponse(\App\Utils\Model\Reponse\Response::error($message));
    }
}//end class
