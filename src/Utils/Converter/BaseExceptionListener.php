<?php
/**
 * Created by PhpStorm.
 * User: asd
 * Date: 10.07.2019
 * Time: 20:28
 */

namespace App\Utils\Converter;

use App\Utils\Exception\Dump;
use App\Utils\Service\DoctrineReconnectHelper;
use App\Utils\Service\ExceptionTranslator;
use \Exception;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Throwable;

/**
 * Class BaseExceptionListener
 *
 */
#[AsEventListener(event: ExceptionEvent::class, method: 'onKernelException')]
class BaseExceptionListener
{
    public function __construct(
        private ExceptionTranslator $exceptionTranslator,
        private DoctrineReconnectHelper $doctrineReconnectHelper,
        private RequestStack $requestStack,
    ) {
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
            \Doctrine\DBAL\Exception::class => $this->makeReconnect($exception),
            default => $this->makeError($exception)
        };

        $response->setStatusCode($this->resolveStatusCode($exception));

        if ($exception instanceof HttpExceptionInterface) {
            foreach ($exception->getHeaders() as $name => $value) {
                $response->headers->set($name, $value);
            }
        }

        $event->setResponse($response);

        return true;

    }//end onKernelException()

    /**
     * Preserves the HTTP status intended by the thrown exception.
     *
     * Domain and DBAL exceptions keep the project's existing envelope and are
     * still reported as 500. Symfony HTTP exceptions (401/403/404/400/409/…)
     * keep their status; security exceptions that fall through get the status
     * Symfony would have assigned them.
     */
    private function resolveStatusCode(Throwable $exception): int
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        if ($exception instanceof AccessDeniedException) {
            return Response::HTTP_FORBIDDEN;
        }

        if ($exception instanceof AuthenticationException) {
            return Response::HTTP_UNAUTHORIZED;
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }//end resolveStatusCode()

    function dump(ExceptionEvent $event): Response
    {
        return new Response((string) $event->getThrowable(), 200);
    }


    function makeFail(\Throwable $exception): JsonResponse
    {
        $message = $this->translate($exception);
        return new JsonResponse(\App\Utils\Model\Reponse\Response::fail(['message' => $message]));
    }

    function makeError(\Throwable $exception): JsonResponse
    {
        $message = $this->translate($exception);
        return new JsonResponse(\App\Utils\Model\Reponse\Response::error($message));
    }

    function makeReconnect(\Throwable $exception): JsonResponse
    {
        $this->doctrineReconnectHelper->reconnectIfNeed();
        return $this->makeError($exception);
    }

    private function translate(\Throwable $exception): string
    {
        $locale = $this->requestStack->getCurrentRequest()?->getLocale();

        return $this->exceptionTranslator->translateThrowable($exception, $locale);
    }
}//end class
