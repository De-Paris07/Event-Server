<?php

namespace App\Listener;

use App\Service\ClientAuthService;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class AuthClientCheckListener
{
    /**
     * @var null|\Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * @var ClientAuthService
     */
    private $auth;

    /**
     * AuthClientCheckListener constructor.
     *
     * @param RequestStack $requestStack
     * @param ClientAuthService $auth
     */
    public function __construct(
        RequestStack $requestStack,
        ClientAuthService $auth
    ) {
        $this->request = $requestStack->getCurrentRequest();
        $this->auth = $auth;
    }

    /**
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if (in_array($this->request->getRequestUri(), ['/success', '/fail', '/event'])) {
            return;
        }

        if ($this->request->getRequestUri() === '/subscribe') {
            return;
        }

        if ($this->request->getRequestUri() === '/test') {
            return;
        }

        if (!$this->auth->isCurrentClientValid()) {
            throw new AccessDeniedException('Access is denied');
        }
    }
}
