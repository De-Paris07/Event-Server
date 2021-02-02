<?php

namespace App\Listener;

use App\Service\ClientAuthService;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

class ApplyAuthToResponseListener
{
    /**
     * @var ClientAuthService
     */
    private $auth;

    /**
     * ApplyAuthToResponseListener constructor.
     * @param ClientAuthService $auth
     */
    public function __construct(ClientAuthService $auth)
    {
        $this->auth = $auth;
    }

    /**
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$this->auth->isCurrentClientValid()) {
            return;
        }

        $response = $event->getResponse();
        $this->auth->applyClientTokenToResponse($response);
        $event->setResponse($response);
    }
}
