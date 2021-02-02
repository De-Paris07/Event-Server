<?php

namespace App\Listener;

use App\Exception\ValidateFormException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExceptionResponseCustomizeListener
{
    /**
     * @param GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();

        $code = Response::HTTP_BAD_REQUEST;

        if ($exception instanceof HttpException) {
            $code = $exception->getStatusCode();
        }

        if ($exception instanceof ValidateFormException) {
            $responseData = [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'errors' => $exception->getErrors(),
                'trace' => $exception->getTrace()
            ];
        } else {
            $responseData = [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTrace()
            ];
        }

        $event->setResponse(new JsonResponse($responseData, $code));
    }
}
